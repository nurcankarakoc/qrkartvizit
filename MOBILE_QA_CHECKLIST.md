# Mobile & Tablet QA Checklist

Bu dokuman, QR Kartvizit projesi icin cihaz bazli responsive QA kontrol listesidir.

## 1) Cihaz Matrisi

Testleri asagidaki viewport profilleriyle yap:

- iPhone SE: `375x667`
- iPhone 14 / 15: `390x844`
- iPhone 14 Pro Max: `430x932`
- Galaxy S8/S9: `360x740`
- Pixel 7: `412x915`
- iPad Mini (Portrait): `768x1024`
- iPad (Landscape): `1024x768`

## 2) Test Edilecek Sayfalar

- `index.php`
- `auth/login.php`
- `auth/register.php`
- `customer/dashboard.php`
- `customer/profile.php`
- `customer/design-tracking.php`
- `designer/dashboard.php`
- `designer/order_details.php?id=<ornek_id>`
- `admin/dashboard.php`
- `admin/orders.php`
- `admin/disputes.php`
- `admin/designers.php`

## 3) Her Sayfada Zorunlu Kontroller

### A) Layout / Responsive

- [ ] Sayfa yatay kaydirma (horizontal overflow) yapmiyor.
- [ ] Kartlar ve tablolar ekran icinde kaliyor.
- [ ] `768px` altinda 2 kolonlu alanlar tek kolona dusuyor.
- [ ] `480px` altinda padding/font boyutlari okunabilir kaliyor.

### B) Mobile Navigation (Panel Sayfalari)

- [ ] Hamburger buton gorunuyor.
- [ ] Hamburger tiklaninca sol menu aciliyor.
- [ ] Overlay tiklaninca menu kapaniyor.
- [ ] Menu linkine tiklayinca menu kapaniyor.
- [ ] `Esc` ile menu kapanabiliyor (masaustu emulasyon).

### C) Dokunmatik (Touch Target)

- [ ] Ana aksiyon butonlari rahat tiklaniyor (`min 44x44`):
  - [ ] Onayla
  - [ ] Revize Iste
  - [ ] QR/Logo indir
  - [ ] Kaydet / Gonder

### D) Gorsel Tasma Kontrolu

- [ ] Musteri logosu mobilde kart disina tasmiyor.
- [ ] QR gorseli mobilde tasmiyor.
- [ ] Taslak gorselleri responsive gorunuyor (`max-width: 100%`).

### E) Form & Mobil Klavye

- [ ] Input odaklandiginda alan gorunur bolgeye kayiyor.
- [ ] iOS/Android otomatik zoom yok (font-size 16+).
- [ ] Giris, Kayit, Profil duzenleme formlarinda klavye alanlari kapatmiyor.

### F) Etkilesim Uyumlulugu

- [ ] Hover olmayan cihazlarda butonlar bozuk davranmiyor.
- [ ] Touch ile ac-kapa/menu/form aksiyonlari calisiyor.

## 4) Hata Kaydi Formati

Her hata icin su formatta not al:

- Sayfa:
- Cihaz/Viewport:
- Adim:
- Beklenen:
- Gerceklesen:
- Ekran goruntusu:
- Oncelik: `Kritik / Yuksek / Orta / Dusuk`

## 5) Kabul Kriteri (Release Gate)

Deploy icin:

- Kritik hata: `0`
- Yuksek hata: `0`
- Orta/Dusuk: kabul edilen backlog kaydi olusturulmus

