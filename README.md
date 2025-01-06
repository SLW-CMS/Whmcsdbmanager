# Whmcs Dbmanager

**Whmcs Dbmanager**, WHMCS üzerinde **veritabanı tablolarını yönetme**, **log tablolarını hızlı temizleme**, **toplu işlemler (export/clean/drop/optimize)** ve **tam veritabanı yedeği alma** gibi işlemleri kolayca yapabileceğiniz bir addon modülüdür.

## Özellikler

- **Tablo Listeleme ve Boyut Görüntüleme**: WHMCS veritabanındaki tabloların boyutlarını ve kayıt sayılarını gösterir.  
- **Varsayılan Temizlik Önerisi**: Sık sık dolan log tablolarını hızlıca temizleyebilmeniz için öneri listesi sunar.  
- **Toplu İşlemler**: Bir veya birden fazla tabloyu seçerek tek tıkla:
  - **Dışarı Aktar (Export, JSON)**  
  - **Boşalt (Truncate)**  
  - **Sil (Drop)**  
  - **Optimize**  
- **Yeni Tablo Oluşturma**: Kolayca basit bir tablo ekleyebilirsiniz.  
- **Tam Veritabanı Yedeği**: `mysqldump` komutuyla `.sql` formatında tam veritabanı yedeği almanızı sağlar. (Sunucunuzda `exec()` fonksiyonu ve `mysqldump` desteği gerekiyorsa.)

## Kurulum

1. **Dosyaları İndirin**  
   - Bu depo içerisindeki `whmcsdbmanager.php` dosyasını (ve varsa diğer dosyaları) edinin.  

2. **WHMCS Addon Dizinine Kopyalayın**  
   - WHMCS kök dizininde şu yolu izleyin:  
     ```
     /path/to/whmcs/modules/addons/whmcsdbmanager/
     ```  
   - `whmcsdbmanager.php` dosyasını **`modules/addons/whmcsdbmanager/`** içine yerleştirin.  
   - Dizin yapısı şöyle görünmeli:  
     ```
     whmcs/
      └── modules/
          └── addons/
              └── whmcsdbmanager/
                  └── whmcsdbmanager.php
     ```

3. **WHMCS Admin Panelinde Aktifleştirin**  
   - Admin paneline girin: **Setup** \> **Addon Modules** (veya **System Settings** \> **Addon Modules**)  
   - **“Whmcs Dbmanager”** eklentisini bulun ve **Activate** butonuna tıklayın.  
   - Gerekirse **Configure** linkine basarak “Manage Access Control” (hangi admin rollerinin göreceği) ayarlarını yapın.

4. **Addon Menüsünden Erişin**  
   - Üst menüde **Addons** \> **Whmcs Dbmanager** sekmesini göreceksiniz.  
   - Tıklayarak modülü çalıştırabilir, tablo listesini görüntüleyebilir, toplu işlemleri yapabilirsiniz.

## Kullanım

- **Tablo Listesi**: Varsayılan olarak 50 tablo görüntülenir. Sayfanın üst kısmındaki “Gösterilecek kayıt sayısı” alanından 50, 100, 300, 500 şeklinde değişiklik yapabilirsiniz.  
- **Toplu İşlemler**: Liste içinden tablo(lar)ı seçip “Dışarı Aktar”, “Boşalt”, “Sil”, “Optimize” butonlarını kullanabilirsiniz.  
  - İşleme başlamadan önce **onay modalı** açılır; “Evet” dediğinizde işlem gerçekleşir, sayfa yenilenir.  
- **Varsayılan Temizlik Önerisi**: Loglar gibi hızla büyüyen tabloların seçili olduğu bir modal açılır. İstediğiniz tabloları işaretleyip “Seçilenleri Temizle” butonu ile hızlı temizlik yapabilirsiniz.  
- **Yeni Tablo Oluştur**: “Yeni tablo oluştur” alanına tablo adını yazıp ekleyebilirsiniz.  
- **Tam Yedek Al**: “Tam Yedek Al” butonuna tıklayın, modal onayını geçtikten sonra `.sql` formatında veritabanı yedeği oluşturulur.  
  - **Önemli**: `exec()` fonksiyonu ve `mysqldump` komutu sunucunuzda etkin değilse bu özellik çalışmayabilir. Aksi hâlde hata mesajı alırsınız.

## Sık Karşılaşılan Sorunlar

1. **Tam Yedek Alma (exec() Hatası)**  
   - Eğer `exec()` fonksiyonu sunucuda devre dışıysa veya `mysqldump` komutu bulunmuyorsa yedek alamazsınız.  
   - Paylaşımlı hosting ortamlarında sıklıkla `exec()` güvenlik nedeniyle kapalı olabilir.  

2. **Tablo Silme / Boşaltma Yetkisi**  
   - WHMCS veritabanında “DROP” veya “TRUNCATE” yetkiniz yoksa işlem hata verebilir.  
   - Lütfen veritabanı kullanıcı yetkilerini kontrol edin veya hosting sağlayıcınıza danışın.

3. **Hata Mesajları**  
   - Çoğu hata, sayfa üstünde **alert** kutusunda gösterilir. Eklenti, WHMCS admin paneli içinde normal bir sayfa gibi çalışır; eğer tabloyu silmenize izin verilmiyorsa “SQL Error” vb. mesajlar görebilirsiniz.

## Gereksinimler

- **WHMCS 8+** (daha eski sürümlerde de çalışabilir ancak Bootstrap/tema farkları olabilir).  
- **PHP 7.2+** (tercihen 7.4 veya 8.x).  
- Sunucuda **`exec()`** ve **`mysqldump`** varsa “Tam Yedek Alma” özelliği aktif olarak kullanılabilir.

## Katkıda Bulunma

- Pull request’ler ve issue’lar açık!  
- Herhangi bir iyileştirme, hata düzeltmesi veya yeni özellik talebi için GitHub üzerinde issue açabilirsiniz.

## Lisans

- Bu eklenti, [MIT lisansı](https://opensource.org/licenses/MIT) ile yayınlanmıştır.  
- Dilediğiniz şekilde düzenleyebilir, dağıtabilirsiniz ancak lisans metnini korumanız gerekir.

## İletişim

- **Geliştirici**: Ali Çömez - Slaweally | [rootali.net](https://rootali.net)  
- **E-posta**: sys@rootali.net  

Herhangi bir konuda yardıma ihtiyacınız olursa iletişim kurmaktan çekinmeyin. 

