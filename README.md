# Zerosoft QR Kartvizit Platformu

Anında dijital profil oluşturulabildiği, tasarım ve baskı süreçlerinin tek bir panelden yönetildiği tam kapsamlı QR Kartvizit ve E-ticaret platformu.

## 🚀 Özellikler

### 1. Kullanıcı Tipleri
- **Müşteri:** Profilini düzenler, sipariş verir, tasarımı onaylar/revize ister.
- **Tasarımcı:** Gelen siparişleri görür, tasarımı hazırlar, müşteriye sunar.
- **Süper Admin (Zerosoft):** Finansal takip, kullanıcı yönetimi ve uyuşmazlık çözümü.

### 2. Paket Seçenekleri
- **Klasik Paket:** Sadece fiziksel baskılı kartvizit.
- **Sadece Panel:** Dijital profil kullanımı (Fiziksel baskı yok).
- **Akıllı Paket:** Dinamik QR kod + Dijital Panel + Fiziksel Kartvizit.

### 3. Akıllı Teknolojiler
- **vCard Entegrasyonu:** Tek tıkla rehbere kaydetme özelliği.
- **Dinamik QR Kod:** Kartvizit basıldıktan sonra bile bilgiler güncellenebilir.
- **Otomatik Abonelik Yönetimi:** Süresi dolan profillerin otomatik kapatılması.

## 🛠 Teknik Altyapı
- **Frontend:** HTML5, CSS3 (Vanilla CSS), JavaScript.
- **Backend:** PHP (Strong Security Filters - XSS/SQL Injection protection).
- **Veritabanı:** MySQL (Relational Schema).
- **Ödeme:** Sanal POS Entegrasyonu (PCI-DSS Uyumlu).

## 📂 Proje Klasör Yapısı

Platform, modülerlik ve güvenlik prensiplerine uygun olarak aşağıdaki düzende kurgulanmıştır:

```text
qrkartvizit/
├── auth/                # GİRİŞ VE KAYIT DOSYALARI BURADA
│   ├── login.php
│   └── register.php
├── database/            # SQL DOSYALARI BURADA
│   └── database.sql
├── customer/            # Müşteri paneli sayfaları
│   ├── dashboard.php
│   ├── design-tracking.php
│   └── profile.php
├── admin/               # Admin paneli (Gelecek planlaması için hazır)
├── designer/            # Tasarımcı paneli (Gelecek planlaması için hazır)
├── core/                # Veritabanı bağlantısı ve çekirdek fonksiyonlar
├── processes/           # Form işleme ve arka plan mantığı
├── assets/              # CSS, JS, Resimler ve Logolar
├── scripts/             # Bakım ve kurulum script'leri
├── index.php            # Ana vitrin sayfası
├── .env                 # Yapılandırma ayarları
├── robots.txt           # SEO ayarları
└── README.md            # Proje rehberi
```

## ⚙ Kurulum
1. `/database/database.sql` dosyasını MySQL veritabanına içe aktarın.
2. `.env` dosyasını kendi veritabanı ve API bilgilerinizle güncelleyin.
3. PHP sunucusunu başlatın.

## ⚖ Hukuki Uyum
- KVKK Aydınlatma Metni ve Veri İmha Modülü mevcuttur.
- Çerez Politikası ve İşlem Rehberi entegre edilmiştir.

---
**Zerosoft Yazılım & Tasarım** tarafından geliştirilmiştir.
