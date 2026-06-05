/*
  ==============================================================
  ESP32-S3 SALOMATLIK MONITOR + OVOZLI AI YORDAMCHI (TUZATILGAN)
  --------------------------------------------------------------
  TUZATISHLAR:
    1) OVOZ UZILISHI:  I2S dinamik DMA buferi kattalashtirildi
       (~60 ms -> ~300 ms). Internet biroz "tutilsa" ham ovoz
       bo'shliqsiz chiqadi.
    2) KICHIK YOZUVLAR: Asosiy ko'rsatkichlar 3-o'lchamda,
       ovqat/holat 2-o'lchamda. Layout soddalashtirildi
       (kichik LED hisoblagichlari ekrandan olib tashlandi,
       LED'lar baribir ishlaydi).

  SALOMATLIK QISMI:
    - MAX30102  -> HR (BPM) + SpO2 + ichki harorat (I2C)
    - ST7789 TFT-> ekran
    - WiFi AP   -> web dashboard (192.168.4.1), EKG, SOS overlay
    - SOS tugma -> GPIO16

  OVOZLI AI QISMI (push-to-talk):
    - INMP441   -> I2S mikrofon (savol)
    - MAX98357A -> I2S dinamik   (javob)
    - Gemini    -> audio -> matn -> ovoz
    - GPIO47 tugma -> bosib turing va savol bering

  PLATFORMA: ESP32 Arduino Core 3.x
  ARDUINO IDE: Board "ESP32S3 Dev Module", PSRAM Disabled,
               Flash 16MB, Huge APP, USB CDC On Boot Enabled.
  ==============================================================
*/

#include <Adafruit_GFX.h>
#include <Adafruit_ST7789.h>
#include <SPI.h>
#include <WiFi.h>
#include <WebServer.h>
#include <Wire.h>
#include "MAX30105.h"
#include "spo2_algorithm.h"

#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <driver/i2s_std.h>


// ============== SALOMATLIK PINLARI ==============
#define TFT_CS    10
#define TFT_DC     5
#define TFT_RST    4
#define TFT_MOSI  11
#define TFT_SCLK  12

#define I2C_SDA    8         // MAX30102
#define I2C_SCL    9

const int LED_COUNT = 3;
const int ledPins[LED_COUNT] = {1, 2, 42};

#define BUTTON_PIN  16       // SOS tugma

// ============== OVOZLI AI PINLARI ==============
// INMP441 (I2S mikrofon - RX)
#define MIC_SCK  14
#define MIC_WS   15
#define MIC_SD   13
// MAX98357A (I2S dinamik - TX)
#define SPK_BCLK 17
#define SPK_LRC  18
#define SPK_DIN  21
// Gapirish (push-to-talk) tugmasi
#define BTN_VOICE 47         // bir oyog'i GPIO47, ikkinchisi GND

// ===================== WiFi SOZLAMALARI ======================
const char* AP_SSID = "salom";          // dashboard AP
const char* AP_PASS = "12345678";
const char* WIFI_SSID = "SHUKURBEK";    // internet (router)
const char* WIFI_PASS = "123456789";

// ===================== GEMINI SOZLAMALARI ====================
const char* GEMINI_API_KEY = "BU_YERGA_YANGI_API_KEY";  // <-- yangi kalit qo'ying
const char* TEXT_MODEL = "gemini-2.5-flash";
const char* TTS_MODEL  = "gemini-2.5-flash-preview-tts";
const char* TTS_VOICE  = "Kore";

#define SR_MIC   16000
#define SR_TTS   24000
#define MAX_REC_SEC 3
#define MIC_SHIFT  11

// Joylashuv
const char* GPS_LAT = "41.3082130";
const char* GPS_LON = "69.2472617";
const float TEMP_ALERT_THRESHOLD = 37.0;

WebServer server(80);

// =================== MAX30102 ===================
MAX30105 particleSensor;
#define SAMPLE_BUF 100
uint32_t irBuf[SAMPLE_BUF];
uint32_t redBuf[SAMPLE_BUF];
uint32_t irLin[SAMPLE_BUF];
uint32_t redLin[SAMPLE_BUF];
int  bufHead = 0, bufFilled = 0, newSinceCalc = 0;
uint32_t lastIR = 0;
unsigned long lastSampleMs = 0;   // MAX30102 oxirgi marta ma'lumot bergan vaqt (watchdog)
const long FINGER_THRESHOLD = 50000;
int32_t spo2Value; int8_t validSPO2;
int32_t heartRateValue; int8_t validHeartRate;
bool fingerPresent = false, oldFinger = false;

unsigned long lastTempRead = 0; const unsigned long TEMP_DELAY = 2000;

// =================== I2S (ovoz) ===================
i2s_chan_handle_t rx_handle = NULL;
i2s_chan_handle_t tx_handle = NULL;
uint8_t* recBuf = NULL;
size_t   recCap = 0;
int      recSec = MAX_REC_SEC;

// --- TO'LIQ YUKLAB-CHALISH ---
// Butun TTS ovozi avval RAM ga (8KB bloklarga) yuklanadi, keyin tarmoqsiz
// uzluksiz chalinadi. Shunda internet tutilsa ham ovoz umuman uzilmaydi.
#define PCM_CHUNK 8192
uint8_t** pcmChunks   = NULL;   // 8KB bloklar ro'yxati
int       pcmChunkCnt = 0;
int       pcmChunkCap = 0;
size_t    pcmTotal    = 0;      // jami yuklangan bayt
bool      pcmOverflow = false;  // xotira to'lib ketdimi

bool pcmAppend(const uint8_t* data, size_t len){
  if(pcmOverflow) return false;
  size_t i=0;
  while(i<len){
    int idx    = pcmTotal / PCM_CHUNK;
    int within = pcmTotal % PCM_CHUNK;
    if(idx >= pcmChunkCnt){
      // tizim/TLS uchun ~45KB zaxira qoldiramiz
      if(ESP.getFreeHeap() < (PCM_CHUNK + 45000)){ pcmOverflow=true; return false; }
      if(pcmChunkCnt >= pcmChunkCap){
        int nc = pcmChunkCap ? pcmChunkCap*2 : 32;
        uint8_t** np = (uint8_t**)realloc(pcmChunks, nc*sizeof(uint8_t*));
        if(!np){ pcmOverflow=true; return false; }
        pcmChunks=np; pcmChunkCap=nc;
      }
      uint8_t* c = (uint8_t*)malloc(PCM_CHUNK);
      if(!c){ pcmOverflow=true; return false; }
      pcmChunks[pcmChunkCnt++]=c;
    }
    size_t space = PCM_CHUNK - within;
    size_t n = (len - i < space) ? (len - i) : space;
    memcpy(pcmChunks[idx]+within, data+i, n);
    pcmTotal += n; i += n;
  }
  return true;
}
void pcmPlayAll(){
  for(int i=0;i<pcmChunkCnt;i++){
    size_t bytes = (i < pcmChunkCnt-1) ? PCM_CHUNK
                                       : (pcmTotal - (size_t)(pcmChunkCnt-1)*PCM_CHUNK);
    size_t even = bytes & ~((size_t)1);
    if(even){ size_t w; i2s_channel_write(tx_handle, pcmChunks[i], even, &w, portMAX_DELAY); }
  }
}
void pcmFree(){
  for(int i=0;i<pcmChunkCnt;i++) free(pcmChunks[i]);
  free(pcmChunks);
  pcmChunks=NULL; pcmChunkCnt=0; pcmChunkCap=0; pcmTotal=0; pcmOverflow=false;
}
static const char B64TAB[] =
  "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";

// LED vaqt sozlamalari
const int LED_ON_TIME = 100;
const int LED_OFF_MIN = 500;
const int LED_OFF_MAX = 1000;
const int CYCLES_PER_MINUTE = 60;
const int CYCLES_PER_LED = CYCLES_PER_MINUTE / LED_COUNT;
int ledSequence[CYCLES_PER_MINUTE];
int sequenceIndex = 0;
bool ledIsOn = false;
unsigned long ledStateTime = 0;
unsigned long currentOffDuration = 0;
int activeLed = 0;
int ledCount[LED_COUNT] = {0, 0, 0};

// SOS
bool sosActive = false;
unsigned long sosTime = 0;
const unsigned long SOS_DURATION = 5000;

// Tugma
int buttonState = HIGH, lastButtonState = HIGH;
unsigned long lastButtonDebounce = 0;
const int DEBOUNCE_DELAY = 50;

// Salomatlik qiymatlari
float currentTemp = 25.0;
int currentHeartRate = 0;
int spo2 = 0;

String g_lastErr = "";   // oxirgi xato xabari (ekranda ko'rsatish uchun)

// --- Yurak urishi va SpO2 ni BARQARORLASHTIRISH (median filtr) ---
// MAX30102 algoritmi ba'zan 110/50 deb sakraydi. Median filtr bunday
// chetga chiqqan qiymatlarni e'tiborsiz qoldirib, barqaror natija beradi.
const int HR_HIST = 7;
int hrHist[HR_HIST]; int hrHistCnt = 0, hrHistIdx = 0;
const int SP_HIST = 5;
int spHist[SP_HIST]; int spHistCnt = 0, spHistIdx = 0;

static int medianOf(int* src, int cnt){
  int a[16];
  for(int i=0;i<cnt;i++) a[i]=src[i];
  for(int i=0;i<cnt-1;i++)
    for(int j=i+1;j<cnt;j++)
      if(a[j]<a[i]){ int t=a[i]; a[i]=a[j]; a[j]=t; }
  return a[cnt/2];
}

unsigned long lastVitalsUpdate = 0;
const unsigned long VITALS_DELAY = 500;

Adafruit_ST7789 tft = Adafruit_ST7789(TFT_CS, TFT_DC, TFT_MOSI, TFT_SCLK, TFT_RST);

// TFT eski qiymatlar
float oldTemp = -999;
int oldHR = -1, oldSpO2 = -1;
String oldFood = "";
int oldBottomKey = -1;   // (sos<<1 | alert) holatini kuzatish

// ============ EKRAN LAYOUT (320x170, rotation 1) ============
const int LEFT_X    = 8;     // chap ustun boshlanishi
const int RIGHT_X   = 150;   // o'ng ustun boshlanishi
const int Y_TITLE   = 2;     // sarlavha (size 2)
const int Y_ROW1    = 30;    // T (chap) | HR (o'ng)   -> size 3
const int Y_ROW2    = 62;    // H (chap) | SpO2 (o'ng) -> size 3
const int Y_FOODLBL = 92;    // "OVQAT TAVSIYASI:" (size 1)
const int Y_FOOD    = 102;   // ovqat matni (size 2)
const int Y_STAT    = 124;   // holat: NORMAL / HARORAT YUQORI / SOS (size 2)
const int Y_BOT     = 150;   // WiFi yoki GPS (size 1)

// ============ TAVSIYALAR ============
// Ekran uchun QISQA (size 2 ga sig'adi)
String getFoodRec(float t) {
  if (t >= TEMP_ALERT_THRESHOLD) return "Sho'rva, suv, choy";
  if (t <= 35.5)                  return "Issiq ovqat, choy";
  return "Meva, sabzavot, asal";
}
// Web uchun TO'LIQ
String getFoodRecWeb(float t) {
  if (t >= TEMP_ALERT_THRESHOLD) return "Yog'siz sho'rva, ko'p suv, limonli choy, apelsin, asal";
  if (t <= 35.5)                  return "Issiq ovqat, asalli choy, yong'oq, mayiz, qaynoq sut";
  return "Yangi sabzavot, mevalar (olma, banan), asal, ko'k choy";
}
bool isTempAlert() { return currentTemp >= TEMP_ALERT_THRESHOLD && currentTemp < 45.0; }

// ============ little-endian / WAV ============
static void wLE16(uint8_t* p, uint16_t v){ p[0]=v; p[1]=v>>8; }
static void wLE32(uint8_t* p, uint32_t v){ p[0]=v; p[1]=v>>8; p[2]=v>>16; p[3]=v>>24; }
static void writeWavHeader(uint8_t* h, uint32_t dataBytes, uint32_t sr){
  uint16_t ch=1, bits=16;
  memcpy(h,"RIFF",4);     wLE32(h+4, 36+dataBytes);
  memcpy(h+8,"WAVE",4);   memcpy(h+12,"fmt ",4);
  wLE32(h+16,16);         wLE16(h+20,1);
  wLE16(h+22,ch);         wLE32(h+24,sr);
  wLE32(h+28, sr*ch*bits/8);  wLE16(h+32, ch*bits/8);
  wLE16(h+34,bits);       memcpy(h+36,"data",4);
  wLE32(h+40,dataBytes);
}
static String jsonEscape(const String& in){
  String out; out.reserve(in.length()+16);
  for(size_t i=0;i<in.length();i++){
    char c=in[i];
    if(c=='\"') out += "\\\"";
    else if(c=='\\') out += "\\\\";
    else if(c=='\n'||c=='\t') out += ' ';
    else if(c=='\r') {}
    else out += c;
  }
  return out;
}

// ============ BodyStream (audio oqim bilan yuborish) ============
class BodyStream : public Stream {
public:
  BodyStream(const String& pre, const uint8_t* wav, size_t wavLen, const String& suf)
    : _pre(pre), _suf(suf), _wav(wav), _wavLen(wavLen), _pos(0) {
    _b64Len = 4 * ((_wavLen + 2) / 3);
    _total  = _pre.length() + _b64Len + _suf.length();
  }
  size_t size() const { return _total; }
  int available() override { return (int)(_total - _pos); }
  int read() override { return (_pos < _total) ? produce(_pos++) : -1; }
  int peek() override { return (_pos < _total) ? produce(_pos)   : -1; }
  size_t readBytes(char* buf, size_t len){
    size_t n=0; while(n<len && _pos<_total) buf[n++]=(char)produce(_pos++); return n;
  }
  size_t write(uint8_t) override { return 0; }
private:
  String _pre, _suf; const uint8_t* _wav; size_t _wavLen;
  size_t _b64Len, _total, _pos;
  uint8_t produce(size_t p){
    size_t preLen=_pre.length();
    if(p<preLen) return (uint8_t)_pre[p];
    if(p<preLen+_b64Len){
      size_t k=p-preLen, grp=k>>2, within=k&3, s=grp*3;
      uint8_t b0=_wav[s];
      uint8_t b1=(s+1<_wavLen)?_wav[s+1]:0;
      uint8_t b2=(s+2<_wavLen)?_wav[s+2]:0;
      switch(within){
        case 0: return B64TAB[b0>>2];
        case 1: return B64TAB[((b0&3)<<4)|(b1>>4)];
        case 2: return (s+1<_wavLen)? B64TAB[((b1&0xF)<<2)|(b2>>6)] : '=';
        default:return (s+2<_wavLen)? B64TAB[b2&0x3F] : '=';
      }
    }
    return (uint8_t)_suf[p - preLen - _b64Len];
  }
};

// ============ TtsPlayStream (javobni darrov chalish) ============
class TtsPlayStream : public Stream {
public:
  TtsPlayStream(): _bn(0),_played(0),_st(0),_match(0),_qn(0),_pad(0){}
  size_t played() const { return _played; }
  int available() override { return 0; }
  int read() override { return -1; }
  int peek() override { return -1; }
  size_t write(uint8_t b) override { feed(b); return 1; }
  size_t write(const uint8_t* buf, size_t size) override { for(size_t i=0;i<size;i++) feed(buf[i]); return size; }
  void finish(){
    if(_bn){ pcmAppend(_buf,_bn); _played+=_bn; _bn=0; }
  }
private:
  uint8_t _buf[2048]; size_t _bn, _played;
  int _st,_match,_q[4],_qn,_pad;
  static int b64val(uint8_t c){
    if(c>='A'&&c<='Z')return c-'A';
    if(c>='a'&&c<='z')return c-'a'+26;
    if(c>='0'&&c<='9')return c-'0'+52;
    if(c=='+')return 62; if(c=='/')return 63; return -1;
  }
  void emit(uint8_t b){
    _buf[_bn++]=b;
    if(_bn>=sizeof(_buf)){ pcmAppend(_buf,_bn); _played+=_bn; _bn=0; }
  }
  void feed(uint8_t c){
    static const char PAT[6]={'\"','d','a','t','a','\"'};
    switch(_st){
      case 0:
        if(c==(uint8_t)PAT[_match]){ _match++; if(_match==6)_st=1; }
        else _match=(c=='\"')?1:0;
        break;
      case 1: if(c=='\"'){ _st=2; _qn=0; _pad=0; } break;
      case 2:
        if(c=='\"'){ _st=3; break; }
        { int v;
          if(c=='='){ _pad++; v=0; } else { v=b64val(c); if(v<0) break; }
          _q[_qn++]=v;
          if(_qn==4){
            emit((_q[0]<<2)|(_q[1]>>4));
            if(_pad<2) emit(((_q[1]&0xF)<<4)|(_q[2]>>2));
            if(_pad<1) emit(((_q[2]&0x3)<<6)|_q[3]);
            _qn=0; _pad=0;
          }
        }
        break;
      default: break;
    }
  }
};

// ============ I2S sozlash ============
void initMic(){
  i2s_chan_config_t cc = I2S_CHANNEL_DEFAULT_CONFIG(I2S_NUM_0, I2S_ROLE_MASTER);
  i2s_new_channel(&cc, NULL, &rx_handle);
  i2s_std_config_t sc = {
    .clk_cfg  = I2S_STD_CLK_DEFAULT_CONFIG(SR_MIC),
    .slot_cfg = I2S_STD_PHILIPS_SLOT_DEFAULT_CONFIG(I2S_DATA_BIT_WIDTH_32BIT, I2S_SLOT_MODE_MONO),
    .gpio_cfg = { .mclk=I2S_GPIO_UNUSED, .bclk=(gpio_num_t)MIC_SCK, .ws=(gpio_num_t)MIC_WS,
                  .dout=I2S_GPIO_UNUSED, .din=(gpio_num_t)MIC_SD, .invert_flags={false,false,false} },
  };
  sc.slot_cfg.slot_mask = I2S_STD_SLOT_LEFT;
  i2s_channel_init_std_mode(rx_handle, &sc);
  i2s_channel_enable(rx_handle);
}

void initSpeaker(){
  // === OVOZ UZILISHINI TUZATISH ===
  // DMA buferi standartda ~60 ms (6 x 240 frame). Bu internet
  // tebranishini "yutib" yetmaydi va ovoz uzilib chiqadi.
  // Quyida ~300 ms (8 x 900 frame) ga oshirildi.
  i2s_chan_config_t cc = I2S_CHANNEL_DEFAULT_CONFIG(I2S_NUM_1, I2S_ROLE_MASTER);
  cc.auto_clear    = true;   // buffer bo'shasa sukunat (shovqin emas)
  cc.dma_desc_num  = 6;      // RAM dan chalamiz, katta DMA shart emas
  cc.dma_frame_num = 480;    // ~120 ms; qolgan RAM ovoz buferi uchun bo'shaydi
  i2s_new_channel(&cc, &tx_handle, NULL);
  i2s_std_config_t sc = {
    .clk_cfg  = I2S_STD_CLK_DEFAULT_CONFIG(SR_TTS),
    .slot_cfg = I2S_STD_PHILIPS_SLOT_DEFAULT_CONFIG(I2S_DATA_BIT_WIDTH_16BIT, I2S_SLOT_MODE_MONO),
    .gpio_cfg = { .mclk=I2S_GPIO_UNUSED, .bclk=(gpio_num_t)SPK_BCLK, .ws=(gpio_num_t)SPK_LRC,
                  .dout=(gpio_num_t)SPK_DIN, .din=I2S_GPIO_UNUSED, .invert_flags={false,false,false} },
  };
  i2s_channel_init_std_mode(tx_handle, &sc);
  i2s_channel_enable(tx_handle);
}

void beep(int freqHz, int durMs, int vol){
  int totalSamples = SR_TTS * durMs / 1000;
  int16_t buf[256]; size_t w;
  float phase=0, inc=2.0f*PI*freqHz/SR_TTS; int done=0;
  while(done<totalSamples){
    int n=(totalSamples-done<256)?(totalSamples-done):256;
    for(int i=0;i<n;i++){ buf[i]=(int16_t)(sinf(phase)*vol); phase+=inc; if(phase>2*PI)phase-=2*PI; }
    i2s_channel_write(tx_handle, buf, n*2, &w, portMAX_DELAY);
    done+=n;
  }
}
void startupSignal(){ for(int i=0;i<3;i++){ beep(1500,120,9000); delay(120);} }

// ============ Yozuv buferi ============
bool allocRecBuf(){
  if(recBuf) return true;
  for(recSec=MAX_REC_SEC; recSec>=1; recSec--){
    recCap=(size_t)SR_MIC*recSec;
    recBuf=(uint8_t*)malloc(44+recCap*2);
    if(recBuf) return true;
  }
  return false;
}
void freeRecBuf(){ if(recBuf){ free(recBuf); recBuf=NULL; } }

// ============ SETUP ============
void setup() {
  Serial.begin(115200);
  delay(600);
  Serial.println("\n=== Salomatlik + Ovozli AI (TUZATILGAN) ===");
  Serial.printf("Bo'sh xotira: %u bayt\n", (unsigned)ESP.getFreeHeap());

  for (int i=0;i<LED_COUNT;i++){ pinMode(ledPins[i],OUTPUT); digitalWrite(ledPins[i],LOW); }
  pinMode(BUTTON_PIN, INPUT_PULLUP);
  pinMode(BTN_VOICE,  INPUT_PULLUP);

  randomSeed(analogRead(0));
  generateSequence();

  // MAX30102
  Wire.begin(I2C_SDA, I2C_SCL);
  if (!particleSensor.begin(Wire, I2C_SPEED_FAST)) {
    Serial.println("MAX30102 TOPILMADI!");
  } else {
    byte ledBrightness=60, sampleAverage=4, ledMode=2, sampleRate=100;
    int pulseWidth=411, adcRange=4096;
    particleSensor.setup(ledBrightness, sampleAverage, ledMode, sampleRate, pulseWidth, adcRange);
    particleSensor.enableDIETEMPRDY();
  }

  tft.init(170, 320);
  tft.setRotation(1);

  // WiFi: AP + STA
  WiFi.mode(WIFI_AP_STA);
  WiFi.softAP(AP_SSID, AP_PASS);
  Serial.printf("AP IP: %s\n", WiFi.softAPIP().toString().c_str());
  WiFi.begin(WIFI_SSID, WIFI_PASS);
  uint32_t t0=millis();
  while(WiFi.status()!=WL_CONNECTED && millis()-t0<10000){ delay(300); Serial.print("."); }
  Serial.println();
  // WiFi holatini ekranda 3 soniya ko'rsatamiz
  tft.fillScreen(ST77XX_BLACK);
  tft.setTextSize(3);
  if(WiFi.status()==WL_CONNECTED){
    Serial.printf("Internet (STA) IP: %s\n", WiFi.localIP().toString().c_str());
    tft.setTextColor(ST77XX_GREEN);
    tft.setCursor(20, 70); tft.print("WiFi: Ulandi");
  } else {
    Serial.println("STA ulanmadi.");
    tft.setTextColor(ST77XX_RED);
    tft.setCursor(8, 70); tft.print("WiFi: Ulanmadi");
  }
  delay(3000);   // 3 soniya turadi, keyin asosiy ekran chiziladi

  server.on("/", handleRoot);
  server.on("/data", handleData);
  server.onNotFound(handleRoot);
  server.begin();

  initMic();
  initSpeaker();
  startupSignal();

  drawMainScreen();
  ledStateTime = millis();
  currentOffDuration = random(LED_OFF_MIN, LED_OFF_MAX + 1);
  lastSampleMs = millis();   // sensor watchdog taymerini boshlash (noto'g'ri tiklanmasin)
  Serial.println("Tayyor. Gaplashish uchun GPIO47 tugmasini bosib turing.");
}

// ============ LOOP ============
void loop() {
  server.handleClient();
  updateLeds();
  pollMax30102();

  if (millis() - lastTempRead >= TEMP_DELAY) { lastTempRead = millis(); readBodyTemp(); }

  if (millis() - lastVitalsUpdate >= VITALS_DELAY) {
    lastVitalsUpdate = millis();
    updateVitalsDisplay();
    updateFoodDisplay();
  }

  checkButton();
  if (sosActive && (millis() - sosTime >= SOS_DURATION)) sosActive = false;
  updateBottom();

  // Push-to-talk (GPIO47)
  if (digitalRead(BTN_VOICE) == LOW) {
    delay(30);
    if (digitalRead(BTN_VOICE) == LOW) {
      handleVoice();
      lastSampleMs = millis();   // uzoq blokdan keyin watchdog noto'g'ri ishlamasin
    }
  }
}

// ============ OVOZLI AI (modal) ============
void handleVoice(){
  if (WiFi.status()!=WL_CONNECTED){ drawVoiceStatus("Internet yo'q", ST77XX_RED); delay(800); drawMainScreen(); return; }
  if (!allocRecBuf()){ drawVoiceStatus("Xotira yetmadi", ST77XX_RED); delay(800); drawMainScreen(); return; }

  drawVoiceStatus("TINGLANYAPTI...", ST77XX_GREEN);
  beep(1200,80,8000);
  size_t samples = recordWhileHeld();

  if (samples < (size_t)SR_MIC/3){ drawVoiceStatus("Juda qisqa", ST77XX_YELLOW); freeRecBuf(); delay(600); drawMainScreen(); return; }

  drawVoiceStatus("AI O'YLAYAPTI...", ST77XX_CYAN);
  g_lastErr = "";
  String answer = askGemini(samples);
  freeRecBuf();

  if (answer.length()==0){
    drawVoiceStatus(g_lastErr.length() ? g_lastErr.c_str() : "Javob yo'q", ST77XX_RED);
    delay(1800); drawMainScreen(); return;
  }

  Serial.println("AI: "+answer);
  // Ovoz yuklanayotgan payt: hali ovoz yo'q, shuning uchun "TAHLIL QILMOQDA"
  drawVoiceStatus("TAHLIL QILMOQDA...", ST77XX_YELLOW);
  speakText(answer);          // ichida: yuklab bo'lgach matn + ovoz TENG boshlanadi
  if (g_lastErr.length()){
    drawVoiceStatus(g_lastErr.c_str(), ST77XX_RED);
    delay(1500);
  } else {
    delay(7000);              // javob matni ovoz tugagach 7 soniya ekranda tursin
  }
  drawMainScreen();
}

size_t recordWhileHeld(){
  int32_t tmp[256]; size_t br;
  for(int i=0;i<8;i++) i2s_channel_read(rx_handle,tmp,sizeof(tmp),&br,pdMS_TO_TICKS(40)); // flush
  int16_t* audio=(int16_t*)(recBuf+44);
  size_t total=0; uint32_t start=millis();
  while(digitalRead(BTN_VOICE)==LOW && total<recCap){
    if(i2s_channel_read(rx_handle,tmp,sizeof(tmp),&br,portMAX_DELAY)!=ESP_OK) continue;
    int n=br/4;
    for(int i=0;i<n && total<recCap;i++){
      int32_t v=tmp[i]>>MIC_SHIFT;
      if(v>32767)v=32767; else if(v<-32768)v=-32768;
      audio[total++]=(int16_t)v;
    }
    if(millis()-start > (uint32_t)recSec*1000) break;
  }
  Serial.printf("Yozildi: %u namuna\n",(unsigned)total);
  return total;
}

String askGemini(size_t samples){
  uint32_t dataBytes=samples*2, wavLen=44+dataBytes;
  writeWavHeader(recBuf, dataBytes, SR_MIC);
  // Jonli sensor qiymatlari - Gemini "holatim qanday?" deb so'ralsa shulardan foydalanadi
  String sensorInfo =
      String("Live sensor readings from the user's wearable health device: ")
      + "temperature = " + String(currentTemp,1) + " C, "
      + "heart rate = " + (fingerPresent ? String(currentHeartRate) + " BPM" : String("no finger on sensor")) + ", "
      + "SpO2 = " + (fingerPresent ? String(spo2) + " %" : String("no finger on sensor")) + ". ";
  String prompt =
      String("You are a friendly health voice assistant built into a wearable device. ")
      + "The audio contains the user's question. " + sensorInfo
      + "If the user asks about their health, condition, vitals or status, answer using these "
        "sensor readings and mention the relevant numbers. If a value looks abnormal, gently "
        "suggest seeing a doctor, but do NOT diagnose. For other questions just answer normally. "
        "Reply in the SAME language as the question (Uzbek, Russian or English). "
        "Keep it to 1-2 short sentences (max ~15 words) for reading aloud. No markdown, no emojis, no symbols.";
  String prefix = String("{\"contents\":[{\"parts\":[{\"text\":\"") + prompt +
                  "\"},{\"inline_data\":{\"mime_type\":\"audio/wav\",\"data\":\"";
  String suffix = "\"}}]}]}";
  BodyStream body(prefix, recBuf, wavLen, suffix);

  WiFiClientSecure client; client.setInsecure(); client.setTimeout(15000); // 15s (oldin 25ms edi!)
  HTTPClient http; http.setTimeout(30000);
  String url = String("https://generativelanguage.googleapis.com/v1beta/models/")
               + TEXT_MODEL + ":generateContent?key=" + GEMINI_API_KEY;
  if(!http.begin(client, url)){ Serial.println("http.begin xato"); g_lastErr="Ulanish xato"; return ""; }
  http.addHeader("Content-Type","application/json");
  int code = http.sendRequest("POST", &body, body.size());
  String answer="";
  if(code==200){
    String resp=http.getString();
    JsonDocument doc;
    if(deserializeJson(doc, resp)==DeserializationError::Ok){
      const char* t=doc["candidates"][0]["content"]["parts"][0]["text"];
      if(t) answer=String(t);
      else  g_lastErr="Bo'sh javob";
    } else {
      g_lastErr="JSON xato";
    }
  } else {
    g_lastErr = "Server xato: " + String(code);
    Serial.printf("HTTP xato: %d\n", code);
    Serial.println(http.getString().substring(0,300));
  }
  http.end();
  return answer;
}

// ============ TTS OVOZNI YUKLAB-CHALISH ============
void speakText(const String& text){
  String body = String("{\"contents\":[{\"parts\":[{\"text\":\"") + jsonEscape(text) +
                "\"}]}],\"generationConfig\":{\"responseModalities\":[\"AUDIO\"],"
                "\"speechConfig\":{\"voiceConfig\":{\"prebuiltVoiceConfig\":{"
                "\"voiceName\":\"" + TTS_VOICE + "\"}}}}}";
  WiFiClientSecure client; client.setInsecure(); client.setTimeout(15000); // 15s (oldin 25ms edi!)
  HTTPClient http; http.setTimeout(30000);
  String url = String("https://generativelanguage.googleapis.com/v1beta/models/")
               + TTS_MODEL + ":generateContent?key=" + GEMINI_API_KEY;
  if(!http.begin(client, url)){ Serial.println("TTS http.begin xato"); g_lastErr="Ulanish xato"; return; }
  http.addHeader("Content-Type","application/json");
  int code=http.POST(body);
  if(code==200){
    // === TO'LIQ YUKLAB-CHALISH ===
    // 1) Butun javob ovozini RAM ga yuklaymiz (tarmoq -> dekod -> pcm bloklar)
    pcmFree();                        // eski ma'lumotni tozalash
    TtsPlayStream player;
    http.writeToStream(&player);      // to'liq yuklab bo'lguncha kutadi
    player.finish();
    Serial.printf("Ovoz yuklandi: %u bayt (~%.1f s), bo'sh xotira: %u\n",
                  (unsigned)pcmTotal, pcmTotal/48000.0f, (unsigned)ESP.getFreeHeap());
    if(pcmOverflow){
      g_lastErr = "Xotira to'ldi";
      Serial.println("OGOH: javob uzun, xotira to'ldi - qisman chalinadi.");
    }
    // 2) ENDI javob matnini chiqaramiz va AYNI SHU PAYT ovozni boshlaymiz -> TENG
    drawAnswerScreen(text);
    pcmPlayAll();
    pcmFree();
  } else {
    g_lastErr = "TTS xato: " + String(code);
    Serial.printf("TTS HTTP xato: %d\n", code);
    Serial.println(http.getString().substring(0,300));
  }
  http.end();
}

// ============ TFT da ovozli holatni ko'rsatish ============
void drawVoiceStatus(const char* msg, uint16_t col){
  // Ko'rsatkichlar maydonini (Row1..Food) tozalaymiz
  tft.fillRect(0, 28, 320, Y_STAT-28, ST77XX_BLACK);
  tft.setTextSize(2);
  tft.setTextColor(col);
  tft.setCursor(10, 54);
  tft.print(msg);
}

// ============ AI JAVOBINI EKRANGA WRAP QILIB CHIQARISH ============
// So'zlarni avtomatik yangi qatorga ko'chiradi. Vertikal joy tugasa to'xtaydi.
void printWrapped(const String& text, int x, int yTop, int xRight, int yBottom,
                  uint8_t sz, uint16_t color){
  tft.setTextSize(sz); tft.setTextColor(color);
  int charW = 6 * sz;
  int lineH = 8 * sz + 4;
  int maxChars = (xRight - x) / charW;
  if (maxChars < 1) maxChars = 1;
  int cy = yTop;
  String line = "", word = "";
  for (int i = 0; i <= (int)text.length(); i++){
    char c = (i < (int)text.length()) ? text[i] : ' ';
    if (c == ' ' || c == '\n' || c == '\r'){
      if (word.length()){
        String trial = line.length() ? line + " " + word : word;
        if ((int)trial.length() <= maxChars){
          line = trial;
        } else {
          if (cy + lineH > yBottom) return;
          tft.setCursor(x, cy); tft.print(line);
          cy += lineH; line = word;
          while ((int)line.length() > maxChars){     // juda uzun so'zni majburan kesish
            if (cy + lineH > yBottom) return;
            tft.setCursor(x, cy); tft.print(line.substring(0, maxChars));
            cy += lineH; line = line.substring(maxChars);
          }
        }
        word = "";
      }
      if (c == '\n'){
        if (cy + lineH > yBottom) return;
        tft.setCursor(x, cy); tft.print(line);
        cy += lineH; line = "";
      }
    } else {
      word += c;
    }
  }
  if (line.length() && cy + lineH <= yBottom){
    tft.setCursor(x, cy); tft.print(line);
  }
}

// AI gapirayotganda javob matnini butun ekranga chiqaradi
void drawAnswerScreen(const String& text){
  tft.fillScreen(ST77XX_BLACK);
  tft.setTextSize(2); tft.setTextColor(ST77XX_MAGENTA);
  tft.setCursor(8, 4); tft.print("AI GAPIRYAPTI...");
  tft.drawLine(6, 24, 314, 24, ST77XX_WHITE);
  // Matn uzun bo'lsa kichikroq shrift bilan ko'proq sig'diramiz
  uint8_t sz = (text.length() > 90) ? 1 : 2;
  printWrapped(text, 8, 32, 314, 166, sz, ST77XX_WHITE);
}

// ============ MAX30102 O'QISH (NON-BLOCKING) ============
// MAX30102 osilib qolsa (I2C uzilsa) qayta ishga tushiradi
void recoverMax30102(){
  Serial.println("MAX30102 javob bermayapti -> qayta ishga tushirilyapti...");
  Wire.end();
  delay(20);
  Wire.begin(I2C_SDA, I2C_SCL);
  Wire.setClock(400000);
  if(particleSensor.begin(Wire, I2C_SPEED_FAST)){
    particleSensor.setup(60, 4, 2, 100, 411, 4096);   // bir xil sozlama
    particleSensor.enableDIETEMPRDY();
    Serial.println("MAX30102 tiklandi.");
  } else {
    Serial.println("MAX30102 hali topilmadi (ulanishni tekshiring).");
  }
  bufHead=0; bufFilled=0; newSinceCalc=0; lastIR=0;
  lastSampleMs = millis();
}

void pollMax30102() {
  particleSensor.check();
  while (particleSensor.available()) {
    uint32_t red = particleSensor.getRed();
    uint32_t ir  = particleSensor.getIR();
    particleSensor.nextSample();
    redBuf[bufHead]=red; irBuf[bufHead]=ir; lastIR=ir;
    bufHead=(bufHead+1)%SAMPLE_BUF;
    if(bufFilled<SAMPLE_BUF) bufFilled++;
    newSinceCalc++;
    lastSampleMs = millis();   // sensor tirik - taymerni yangilaymiz
  }

  // WATCHDOG: agar sensor ~2.5s davomida hech narsa bermasa - osilgan, tiklaymiz
  if (millis() - lastSampleMs > 2500) { recoverMax30102(); return; }
  fingerPresent = (lastIR > FINGER_THRESHOLD);
  if(!fingerPresent){
    currentHeartRate=0; spo2=0;
    hrHistCnt=0; hrHistIdx=0; spHistCnt=0; spHistIdx=0;   // yangi o'lchov uchun reset
    return;
  }

  if(bufFilled>=SAMPLE_BUF && newSinceCalc>=25){
    newSinceCalc=0;
    int idx=bufHead;
    for(int i=0;i<SAMPLE_BUF;i++){ irLin[i]=irBuf[idx]; redLin[i]=redBuf[idx]; idx=(idx+1)%SAMPLE_BUF; }
    maxim_heart_rate_and_oxygen_saturation(irLin, SAMPLE_BUF, redLin,
        &spo2Value,&validSPO2,&heartRateValue,&validHeartRate);
    if(validHeartRate && heartRateValue>30 && heartRateValue<220){
      hrHist[hrHistIdx]=heartRateValue; hrHistIdx=(hrHistIdx+1)%HR_HIST;
      if(hrHistCnt<HR_HIST) hrHistCnt++;
      currentHeartRate = medianOf(hrHist, hrHistCnt);   // sakramaydigan barqaror qiymat
    }
    if(validSPO2 && spo2Value>0 && spo2Value<=100){
      spHist[spHistIdx]=spo2Value; spHistIdx=(spHistIdx+1)%SP_HIST;
      if(spHistCnt<SP_HIST) spHistCnt++;
      spo2 = medianOf(spHist, spHistCnt);
    }
  }
}
void readBodyTemp(){ float t=particleSensor.readTemperature(); if(!isnan(t)) currentTemp=t; }

// ============ ASOSIY EKRAN ============
void drawMainScreen() {
  tft.fillScreen(ST77XX_BLACK);

  // Sarlavha (size 2)
  tft.setTextColor(ST77XX_CYAN); tft.setTextSize(2);
  tft.setCursor(46, Y_TITLE); tft.print("SALOMATLIK MONITOR");
  tft.drawLine(6, 24, 314, 24, ST77XX_WHITE);

  // Ovqat tavsiyasi yorlig'i (statik, size 1)
  tft.setTextSize(1); tft.setTextColor(ST77XX_MAGENTA);
  tft.setCursor(LEFT_X, Y_FOODLBL); tft.print("OVQAT TAVSIYASI:");

  // Eski qiymatlarni reset (qayta chizish uchun)
  oldTemp=-999; oldHR=-1; oldSpO2=-1; oldFinger=!fingerPresent;
  oldFood=""; oldBottomKey=-1;
}

// Yorliq + qiymat (o'lcham parametri bilan)
void drawLabeledValue(int x, int y, uint8_t sz, const char* label, uint16_t labelColor,
                      const String& value, uint16_t valColor) {
  tft.setTextSize(sz); tft.setTextColor(labelColor); tft.setCursor(x,y); tft.print(label);
  tft.setTextColor(valColor); tft.print(value);
}

void updateVitalsDisplay() {
  // T (chap, qator 1) - size 3
  if (currentTemp != oldTemp) {
    tft.fillRect(0, Y_ROW1, RIGHT_X, 26, ST77XX_BLACK);
    String v=String(currentTemp,1)+"C";
    uint16_t col=isTempAlert()?ST77XX_RED:ST77XX_WHITE;
    drawLabeledValue(LEFT_X,Y_ROW1,3,"T:",ST77XX_YELLOW,v,col); oldTemp=currentTemp;
  }
  // HR (o'ng, qator 1) - size 3
  if (currentHeartRate != oldHR || fingerPresent != oldFinger) {
    tft.fillRect(RIGHT_X, Y_ROW1, 320-RIGHT_X, 26, ST77XX_BLACK);
    String hrStr=fingerPresent?String(currentHeartRate):String("--");
    drawLabeledValue(RIGHT_X,Y_ROW1,3,"HR:",ST77XX_RED,hrStr,ST77XX_WHITE); oldHR=currentHeartRate;
  }
  // SpO2 (chap, qator 2) - size 3
  if (spo2 != oldSpO2 || fingerPresent != oldFinger) {
    tft.fillRect(0, Y_ROW2, 320, 26, ST77XX_BLACK);
    String sStr=fingerPresent?(String(spo2)+"%"):String("--");
    drawLabeledValue(LEFT_X,Y_ROW2,3,"SpO2:",ST77XX_CYAN,sStr,ST77XX_WHITE); oldSpO2=spo2;
  }
  oldFinger=fingerPresent;
}

void updateFoodDisplay() {
  String now=getFoodRec(currentTemp);
  if(now!=oldFood){
    tft.fillRect(0, Y_FOOD, 320, 18, ST77XX_BLACK);
    tft.setTextSize(2); tft.setTextColor(ST77XX_WHITE);
    tft.setCursor(LEFT_X, Y_FOOD); tft.print(now);
    oldFood=now;
  }
}

// Pastki maydon: SOS / HARORAT YUQORI / NORMAL holati
void redrawBottom(){
  tft.fillRect(0, Y_STAT, 320, 170-Y_STAT, ST77XX_BLACK);
  tft.setTextSize(2);
  if(sosActive){
    tft.setTextColor(ST77XX_RED);
    tft.setCursor(8, Y_STAT); tft.print("!! SOS YUBORILDI !!");
    tft.setTextSize(1); tft.setTextColor(ST77XX_YELLOW); tft.setCursor(8, Y_BOT);
    tft.print("GPS: "); tft.print(GPS_LAT); tft.print(", "); tft.print(GPS_LON);
  } else if(isTempAlert()){
    tft.setTextColor(ST77XX_RED);
    tft.setCursor(8, Y_STAT); tft.print("! HARORAT YUQORI !");
    tft.setTextSize(1); tft.setTextColor(ST77XX_YELLOW); tft.setCursor(8, Y_BOT);
    tft.print("GPS: "); tft.print(GPS_LAT); tft.print(", "); tft.print(GPS_LON);
  } else {
    tft.setTextColor(ST77XX_GREEN);
    tft.setCursor(8, Y_STAT); tft.print("Holat: NORMAL");
    tft.setTextSize(1); tft.setTextColor(ST77XX_CYAN); tft.setCursor(8, Y_BOT);
    tft.print("WiFi: "); tft.print(AP_SSID); tft.print(" / 192.168.4.1");
  }
}
void updateBottom(){
  int key = (sosActive?2:0) | (isTempAlert()?1:0);
  if(key==oldBottomKey) return;
  oldBottomKey=key;
  redrawBottom();
}

void checkButton() {
  int reading=digitalRead(BUTTON_PIN);
  if(reading!=lastButtonState) lastButtonDebounce=millis();
  if((millis()-lastButtonDebounce)>DEBOUNCE_DELAY){
    if(reading!=buttonState){
      buttonState=reading;
      if(buttonState==LOW){
        sosActive=true; sosTime=millis();
        Serial.print("SOS! "); Serial.print(GPS_LAT); Serial.print(", "); Serial.println(GPS_LON);
      }
    }
  }
  lastButtonState=reading;
}

// ============ LED ============
void generateSequence() {
  int index=0;
  for(int led=0;led<LED_COUNT;led++) for(int i=0;i<CYCLES_PER_LED;i++) ledSequence[index++]=led;
  for(int i=CYCLES_PER_MINUTE-1;i>0;i--){ int j=random(0,i+1); int t=ledSequence[i]; ledSequence[i]=ledSequence[j]; ledSequence[j]=t; }
  sequenceIndex=0; for(int i=0;i<LED_COUNT;i++) ledCount[i]=0;
}
void updateLeds() {
  unsigned long now=millis();
  if(ledIsOn){
    if(now-ledStateTime>=LED_ON_TIME){ digitalWrite(ledPins[activeLed],LOW); ledIsOn=false; ledStateTime=now; currentOffDuration=random(LED_OFF_MIN,LED_OFF_MAX+1); }
  } else {
    if(now-ledStateTime>=currentOffDuration){
      activeLed=ledSequence[sequenceIndex]; sequenceIndex++; ledCount[activeLed]++;
      if(sequenceIndex>=CYCLES_PER_MINUTE) generateSequence();
      digitalWrite(ledPins[activeLed],HIGH); ledIsOn=true; ledStateTime=now;
    }
  }
}

// ============ WEB HANDLERS ============
void handleData() {
  bool alert=isTempAlert();
  String food=getFoodRecWeb(currentTemp);
  String json="{";
  json+="\"temp\":"+String(currentTemp,1)+",";
  json+="\"hr\":"+String(currentHeartRate)+",";
  json+="\"spo2\":"+String(spo2)+",";
  json+="\"finger\":"+String(fingerPresent?"true":"false")+",";
  json+="\"sos\":"+String(sosActive?"true":"false")+",";
  json+="\"alert\":"+String(alert?"true":"false")+",";
  json+="\"food\":\""+food+"\",";
  json+="\"gps_lat\":\""+String(GPS_LAT)+"\",";
  json+="\"gps_lon\":\""+String(GPS_LON)+"\"";
  json+="}";
  server.sendHeader("Cache-Control","no-store");
  server.send(200,"application/json",json);
}

void handleRoot() {
  static const char PAGE[] PROGMEM = R"HTML(<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Salomatlik Monitoring</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:linear-gradient(135deg,#0f0c29 0%,#302b63 50%,#24243e 100%);min-height:100vh;color:#fff;padding:18px;overflow-x:hidden}
.container{max-width:1100px;margin:0 auto}
.header{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:10px}
h1{font-size:26px;font-weight:800;background:linear-gradient(90deg,#00f2fe,#4facfe,#a78bfa);-webkit-background-clip:text;background-clip:text;color:transparent;letter-spacing:.5px}
.status{display:flex;align-items:center;gap:8px;font-size:13px;color:#a0a0c0}
.dot{width:10px;height:10px;border-radius:50%;background:#22c55e;box-shadow:0 0 8px #22c55e;animation:pulse 1.5s infinite}
@keyframes pulse{50%{opacity:.4}}
.alert-banner{display:none;background:linear-gradient(90deg,#dc2626,#991b1b);border-radius:16px;padding:18px 22px;margin-bottom:18px;align-items:center;gap:18px;animation:alertPulse 1.1s infinite;box-shadow:0 10px 30px rgba(220,38,38,.5);border:2px solid rgba(255,255,255,.2)}
.alert-banner.active{display:flex}
.alert-icon{font-size:46px;animation:shake .4s infinite}
.alert-content{flex:1;min-width:0}
.alert-title{font-size:24px;font-weight:900;letter-spacing:1px;color:#fff;margin-bottom:6px}
.alert-loc{font-size:13px;color:#fde68a}
.alert-loc strong{color:#fff;font-weight:700;letter-spacing:.5px}
@keyframes alertPulse{50%{box-shadow:0 10px 30px rgba(220,38,38,.9)}}
@keyframes shake{0%,100%{transform:translateX(0) rotate(0)}25%{transform:translateX(-4px) rotate(-5deg)}75%{transform:translateX(4px) rotate(5deg)}}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px}
.card{background:rgba(255,255,255,.06);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);border:1px solid rgba(255,255,255,.12);border-radius:18px;padding:18px 20px;box-shadow:0 8px 32px rgba(0,0,0,.25);transition:all .25s}
.card:hover{transform:translateY(-3px)}
.card.warn{border-color:rgba(239,68,68,.5);background:rgba(239,68,68,.1)}
.icon{font-size:24px;margin-bottom:8px}
.label{font-size:11px;color:#a0a0c0;text-transform:uppercase;letter-spacing:1.4px;font-weight:600;margin-bottom:6px}
.value{font-size:30px;font-weight:800;line-height:1.1}
.unit{font-size:14px;color:#a0a0c0;margin-left:5px;font-weight:500}
.sub{font-size:11px;color:#7a7a96;margin-top:4px}
.c-temp{border-left:3px solid #fb7185}
.c-hr{border-left:3px solid #f43f5e}
.c-spo2{border-left:3px solid #22d3ee}
.food-card{grid-column:1/-1;border-left:3px solid #fbbf24}
.food-text{font-size:17px;font-weight:600;line-height:1.4;color:#fef3c7;margin-top:4px}
.ecg-card{grid-column:1/-1;padding:16px;background:rgba(0,0,0,.5)}
.ecg-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;flex-wrap:wrap;gap:10px}
.ecg-title{font-size:13px;color:#a0a0c0;text-transform:uppercase;letter-spacing:1.4px;font-weight:600}
.ecg-info{display:flex;align-items:center;gap:14px;font-size:12px;color:#9ca3af;flex-wrap:wrap}
.ecg-bpm{color:#22c55e;font-weight:700}
.ecg-bpm span{font-size:18px}
.ecg-live{display:flex;align-items:center;gap:6px;font-size:11px;color:#22c55e}
.live-dot{width:8px;height:8px;border-radius:50%;background:#22c55e;animation:pulse 1s infinite}
.ecg-wrap{position:relative;background:#000;border-radius:10px;overflow:hidden;border:1px solid rgba(0,255,128,.15)}
canvas{width:100%;height:220px;display:block}
.ecg-labels{position:absolute;top:8px;left:10px;font-size:10px;color:rgba(0,255,128,.6);font-family:monospace;line-height:1.4;pointer-events:none}
.ecg-labels b{color:#00ff88}
.footer{text-align:center;margin-top:20px;font-size:11px;color:#5a5a78}
.sos-overlay{position:fixed;inset:0;background:rgba(220,0,0,.92);display:none;align-items:center;justify-content:center;z-index:9999;animation:flash .55s infinite alternate;padding:20px}
.sos-overlay.active{display:flex}
.sos-box{text-align:center;padding:24px;color:#fff;max-width:520px;width:100%}
.sos-icon-big{font-size:78px;margin-bottom:10px;animation:shake .4s infinite}
.sos-title{font-size:64px;font-weight:900;letter-spacing:6px;margin-bottom:14px;text-shadow:0 4px 20px rgba(0,0,0,.5)}
.sos-text{font-size:22px;font-weight:700;letter-spacing:1px;margin-bottom:18px}
.sos-loc-box{background:rgba(0,0,0,.35);border:2px solid rgba(255,255,255,.3);border-radius:14px;padding:16px 18px;backdrop-filter:blur(8px)}
.sos-loc-label{font-size:12px;color:#fde68a;text-transform:uppercase;letter-spacing:2px;margin-bottom:6px;font-weight:700}
.sos-loc-coords{font-size:20px;font-weight:800;font-family:monospace;letter-spacing:.5px;margin-bottom:10px;word-break:break-all}
.sos-loc-link{display:inline-block;background:#fff;color:#dc2626;padding:10px 20px;border-radius:10px;text-decoration:none;font-weight:800;font-size:14px;letter-spacing:.5px;transition:transform .15s}
.sos-loc-link:active{transform:scale(.95)}
@keyframes flash{from{background:rgba(220,0,0,.9)}to{background:rgba(150,0,0,.98)}}
@media(max-width:480px){h1{font-size:20px}.value{font-size:24px}.sos-title{font-size:46px;letter-spacing:4px}.sos-text{font-size:17px}.sos-icon-big{font-size:60px}.sos-loc-coords{font-size:15px}.alert-title{font-size:18px}.alert-icon{font-size:36px}.food-text{font-size:15px}canvas{height:170px}}
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <h1>SALOMATLIK MONITORING</h1>
    <div class="status"><span class="dot"></span><span>Real vaqt</span></div>
  </div>

  <div class="alert-banner" id="alert">
    <div class="alert-icon">&#x26A0;</div>
    <div class="alert-content">
      <div class="alert-title">HARORAT YUQORI!</div>
      <div class="alert-loc">Joylashuv aniqlandi: <strong id="gps">--</strong></div>
    </div>
  </div>

  <div class="grid">
    <div class="card c-temp" id="cardTemp"><div class="icon">&#x1F321;&#xFE0F;</div><div class="label">Harorat</div><div class="value" id="temp">--<span class="unit">&deg;C</span></div><div class="sub" id="temp-sub">MAX30102 (ichki)</div></div>
    <div class="card c-hr"><div class="icon">&#x2764;&#xFE0F;</div><div class="label">Yurak urishi</div><div class="value" id="hr">--<span class="unit">BPM</span></div><div class="sub" id="hr-sub">Barmoq qo'ying</div></div>
    <div class="card c-spo2"><div class="icon">&#x1FAC1;</div><div class="label">SpO&#x2082;</div><div class="value" id="spo2">--<span class="unit">%</span></div><div class="sub">Kislorod darajasi</div></div>

    <div class="card food-card">
      <div class="icon">&#x1F37D;&#xFE0F;</div>
      <div class="label">Taom tavsiyasi</div>
      <div class="food-text" id="food">--</div>
    </div>

    <div class="card ecg-card">
      <div class="ecg-head">
        <span class="ecg-title">EKG &mdash; II otvedeniye (Lead II)</span>
        <div class="ecg-info">
          <span class="ecg-bpm"><span id="bpmBig">--</span> BPM</span>
          <span>25 mm/s &middot; 10 mm/mV</span>
          <span class="ecg-live"><span class="live-dot"></span>LIVE</span>
        </div>
      </div>
      <div class="ecg-wrap">
        <canvas id="ecg"></canvas>
        <div class="ecg-labels">
          <div><b>P</b> &middot; <b>QRS</b> &middot; <b>T</b></div>
          <div>Lead II</div>
        </div>
      </div>
    </div>
  </div>
  <div class="footer">ESP32-S3 &middot; MAX30102 + Gemini &middot; 192.168.4.1</div>
</div>

<div class="sos-overlay" id="sos">
  <div class="sos-box">
    <div class="sos-icon-big">&#x26A0;</div>
    <div class="sos-title">SOS</div>
    <div class="sos-text">Joylashuv aniqlandi</div>
    <div class="sos-loc-box">
      <div class="sos-loc-label">&#x1F4CD; GPS KOORDINATA</div>
      <div class="sos-loc-coords" id="sosCoords">41.3082130, 69.2472617</div>
      <a class="sos-loc-link" id="sosMapLink" href="https://www.google.com/maps?q=41.3082130,69.2472617" target="_blank" rel="noopener">Xaritada ko'rish</a>
    </div>
  </div>
</div>

<script>
var canvas = document.getElementById('ecg');
var ctx = canvas.getContext('2d');
var DPR = window.devicePixelRatio || 1;
function resize(){ var w=canvas.offsetWidth,h=canvas.offsetHeight; canvas.width=w*DPR; canvas.height=h*DPR; ctx.setTransform(DPR,0,0,DPR,0,0); }
window.addEventListener('resize', resize); resize();

var bpm = 75;
var noFinger = true;
var mmPerSec=25, pxPerMm=4, smallGrid=pxPerMm, bigGrid=pxPerMm*5, gridSpeed=mmPerSec*pxPerMm;
var signal=[], sweepX=0, lastT=performance.now(), gridOffset=0;

function ecgWave(t){
  var p= 0.15*Math.exp(-Math.pow((t-0.14)/0.022,2));
  var q=-0.10*Math.exp(-Math.pow((t-0.30)/0.008,2));
  var r= 1.20*Math.exp(-Math.pow((t-0.33)/0.010,2));
  var s=-0.30*Math.exp(-Math.pow((t-0.36)/0.012,2));
  var tw=0.30*Math.exp(-Math.pow((t-0.62)/0.040,2));
  var noise=(Math.random()-0.5)*0.012;
  return p+q+r+s+tw+noise;
}
var startTime = performance.now()/1000;
function getSampleAtTime(timeSec){
  if(noFinger || bpm<=0) return 0;
  var beatDur=60/bpm, rel=timeSec%beatDur;
  if(rel<0) rel+=beatDur;
  return ecgWave(rel/beatDur);
}
function drawGrid(){
  var w=canvas.offsetWidth,h=canvas.offsetHeight;
  ctx.fillStyle='#000'; ctx.fillRect(0,0,w,h);
  ctx.strokeStyle='rgba(0,180,80,0.12)'; ctx.lineWidth=1; ctx.beginPath();
  var offset=gridOffset%smallGrid;
  for(var x=-offset;x<w;x+=smallGrid){ ctx.moveTo(x,0); ctx.lineTo(x,h); }
  for(var y=0;y<h;y+=smallGrid){ ctx.moveTo(0,y); ctx.lineTo(w,y); }
  ctx.stroke();
  ctx.strokeStyle='rgba(0,220,100,0.28)'; ctx.lineWidth=1; ctx.beginPath();
  var bigOffset=gridOffset%bigGrid;
  for(var x=-bigOffset;x<w;x+=bigGrid){ ctx.moveTo(x,0); ctx.lineTo(x,h); }
  for(var y=0;y<h;y+=bigGrid){ ctx.moveTo(0,y); ctx.lineTo(w,y); }
  ctx.stroke();
  ctx.strokeStyle='rgba(0,255,128,0.08)'; ctx.lineWidth=1; ctx.beginPath();
  ctx.moveTo(0,h*0.55); ctx.lineTo(w,h*0.55); ctx.stroke();
}
function drawGridBand(startX,width){
  var w=canvas.offsetWidth,h=canvas.offsetHeight;
  ctx.strokeStyle='rgba(0,180,80,0.12)'; ctx.lineWidth=1; ctx.beginPath();
  var off=gridOffset%smallGrid;
  for(var x=-off;x<w;x+=smallGrid){ if(x+1<startX)continue; if(x>startX+width)break; ctx.moveTo(x,0); ctx.lineTo(x,h); }
  for(var y=0;y<h;y+=smallGrid){ ctx.moveTo(startX,y); ctx.lineTo(startX+width,y); }
  ctx.stroke();
  ctx.strokeStyle='rgba(0,220,100,0.28)'; ctx.lineWidth=1; ctx.beginPath();
  var bOff=gridOffset%bigGrid;
  for(var x=-bOff;x<w;x+=bigGrid){ if(x+1<startX)continue; if(x>startX+width)break; ctx.moveTo(x,0); ctx.lineTo(x,h); }
  for(var y=0;y<h;y+=bigGrid){ ctx.moveTo(startX,y); ctx.lineTo(startX+width,y); }
  ctx.stroke();
  ctx.strokeStyle='rgba(0,255,128,0.08)'; ctx.lineWidth=1; ctx.beginPath();
  ctx.moveTo(startX,h*0.55); ctx.lineTo(startX+width,h*0.55); ctx.stroke();
}
function render(now){
  var w=canvas.offsetWidth,h=canvas.offsetHeight;
  var dt=(now-lastT)/1000; lastT=now; if(dt>0.1)dt=0.1;
  gridOffset+=gridSpeed*dt;
  var advance=gridSpeed*dt, prevX=sweepX; sweepX+=advance;
  var nowSec=now/1000, sample=getSampleAtTime(nowSec-startTime);
  var baseline=h*0.55, pxPerMv=10*pxPerMm, y=baseline-sample*pxPerMv;
  if(sweepX>=w){ sweepX=sweepX-w; signal=[]; drawGrid(); }
  var eraserW=24; ctx.fillStyle='#000';
  if(sweepX+eraserW<=w){ ctx.fillRect(sweepX,0,eraserW,h); }
  else { ctx.fillRect(sweepX,0,w-sweepX,h); ctx.fillRect(0,0,(sweepX+eraserW)-w,h); }
  drawGridBand(sweepX,eraserW);
  signal.push({x:sweepX,y:y,prevX:prevX});
  if(signal.length>1500) signal.splice(0,signal.length-1500);
  ctx.strokeStyle='#00ff66'; ctx.lineWidth=2; ctx.shadowColor='#00ff66'; ctx.shadowBlur=8;
  ctx.lineJoin='round'; ctx.lineCap='round'; ctx.beginPath();
  var started=false;
  for(var i=1;i<signal.length;i++){
    var a=signal[i-1], b=signal[i];
    if(b.x<a.x){ started=false; continue; }
    if(!started){ ctx.moveTo(a.x,a.y); started=true; }
    ctx.lineTo(b.x,b.y);
  }
  ctx.stroke(); ctx.shadowBlur=0;
  ctx.fillStyle='#00ff88'; ctx.shadowColor='#00ff88'; ctx.shadowBlur=12;
  ctx.beginPath(); ctx.arc(sweepX,y,3,0,Math.PI*2); ctx.fill(); ctx.shadowBlur=0;
  requestAnimationFrame(render);
}
drawGrid(); requestAnimationFrame(render);

var sosShown=false;
function showSos(lat,lon){
  if(sosShown) return; sosShown=true;
  document.getElementById('sosCoords').textContent=lat+', '+lon;
  document.getElementById('sosMapLink').href='https://www.google.com/maps?q='+lat+','+lon;
  var el=document.getElementById('sos'); el.classList.add('active');
  setTimeout(function(){ el.classList.remove('active'); sosShown=false; },5000);
}
function hrSub(hr){ if(hr<60)return 'Past (bradikardiya)'; if(hr>100)return 'Yuqori (taxikardiya)'; return 'Normal'; }

function update(){
  fetch('/data').then(function(r){return r.json();}).then(function(d){
    document.getElementById('temp').innerHTML=d.temp.toFixed(1)+'<span class="unit">&deg;C</span>';
    if(d.finger){
      document.getElementById('hr').innerHTML=d.hr+'<span class="unit">BPM</span>';
      document.getElementById('hr-sub').textContent=hrSub(d.hr);
      document.getElementById('spo2').innerHTML=d.spo2+'<span class="unit">%</span>';
      document.getElementById('bpmBig').textContent=d.hr;
      if(d.hr>0){ bpm=d.hr; } noFinger=false;
    } else {
      document.getElementById('hr').innerHTML='--<span class="unit">BPM</span>';
      document.getElementById('hr-sub').textContent="Barmoqni sensorga qo'ying";
      document.getElementById('spo2').innerHTML='--<span class="unit">%</span>';
      document.getElementById('bpmBig').textContent='--'; noFinger=true;
    }
    document.getElementById('food').textContent=d.food;
    var alertEl=document.getElementById('alert'), card=document.getElementById('cardTemp'), tempSub=document.getElementById('temp-sub');
    if(d.alert){
      alertEl.classList.add('active');
      document.getElementById('gps').textContent=d.gps_lat+', '+d.gps_lon;
      card.classList.add('warn'); tempSub.textContent='YUQORI - shifokorga murojaat qiling'; tempSub.style.color='#fca5a5';
    } else {
      alertEl.classList.remove('active'); card.classList.remove('warn');
      tempSub.textContent='MAX30102 (ichki)'; tempSub.style.color='';
    }
    if(d.sos) showSos(d.gps_lat,d.gps_lon);
  }).catch(function(e){});
}
setInterval(update,500); update();
</script>
</body>
</html>)HTML";
  server.send_P(200, "text/html", PAGE);
}
