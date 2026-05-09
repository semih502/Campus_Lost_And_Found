# Campus Lost & Found — İnternet Tabanlı Programlama Teknik Raporu

## 1. Projenin Amacı ve Ana İşlev

**Campus Lost & Found**, üniversite kampüsünde kaybolan ya da bulunan eşyaların dijital ortamda ilan edilmesini ve sahiplerine ulaştırılmasını sağlayan bir web uygulamasıdır.

### Temel İş Akışı

| Adım | Sayfa | Açıklama |
|------|-------|-----------|
| 1 | `04_auth.html` | Kullanıcı kayıt/giriş |
| 2 | `03_create-ad.html` | İlan oluşturma (kayıp/bulunan) |
| 3 | `01_index.html` | İlanları listeleme ve filtreleme |
| 4 | `02_details.html` | İlan detayı görüntüleme |
| 5 | `06_messages.html` | İlan sahibiyle mesajlaşma |
| 6 | `05_dashboard.html` | Kullanıcı kontrol paneli |

Uygulama **HTML + CSS + JavaScript** (ön yüz) ve **PHP + MySQL** (arka yüz) teknoloji çiftiyle geliştirilmiştir. İstemci-sunucu iletişimi sayfa yenilenmesi olmaksızın **AJAX (Fetch API)** aracılığıyla gerçekleştirilmektedir.

---

## 2. Kullanıcı Kimlik Doğrulaması

### 2.1 HTML5 / JavaScript Tarafı Doğrulama — `04_auth.html`

Giriş ve kayıt formları `novalidate` niteliğiyle tanımlanmıştır; bu sayede tarayıcının yerel doğrulaması devre dışı bırakılarak kontrol tamamen JavaScript'e devredilmiştir.

**Snippet — Kayıt Formu HTML:**
```html
<form action="php/register_process.php" method="POST" id="registerForm" novalidate>
    <input type="text"  id="regFullName"  name="full_name"
           maxlength="100" required>
    <input type="email" id="regEmail"     name="email"    required>
    <select id="regUserType" name="user_type" required>
        <option value="student">Student</option>
        <option value="staff">Staff / Personnel</option>
    </select>
    <input type="text"  id="regOfficialId" name="official_id"
           pattern="\d{10}" maxlength="10" required>
    <input type="password" id="regPassword" name="password"
           minlength="8" required>
    <input type="password" id="regPasswordConfirm" name="password_confirm" required>
</form>
```

**Açıklama:**
- `pattern="\d{10}"` → Tarayıcı düzeyinde 10 haneli sayı regex kontrolü.
- `minlength="8"` → Şifre minimum uzunluk kontrolü.
- `required` niteliği → Boş alan koruması.

**Snippet — JavaScript Şifre Eşleşme Kontrolü (`04_auth.html`, satır 418–423):**
```javascript
const password = document.getElementById('regPassword').value;
const confirm  = document.getElementById('regPasswordConfirm').value;

if (password !== confirm) {
    document.getElementById('passwordMismatch').style.display = 'block';
    return; // Formu sunucuya gönderme
}
```

**Açıklama:** Şifreler eşleşmiyorsa `return` ile fonksiyon erken sonlandırılır ve sunucuya hiç istek gönderilmez; bu istemci taraflı ilk güvenlik katmanıdır.

**Snippet — HTML5 Form Geçerlilik Kontrolü:**
```javascript
if (!form.checkValidity()) {
    form.classList.add('was-validated'); // Bootstrap hata stillerini tetikler
    return;
}
```

**Açıklama:** `checkValidity()` metodu tüm `required`, `pattern`, `minlength` gibi HTML5 kısıtlamalarını tek seferde denetler. `was-validated` sınıfı eklenerek Bootstrap'in kırmızı kenarlık/hata mesajı görünümü aktifleştirilir.

---

### 2.2 PHP Sunucu Tarafı Doğrulama — `register_process.php`

**"Never trust user input"** prensibi gereği JavaScript doğrulamaları atlanabilir olduğundan aynı kontroller sunucuda da tekrarlanmaktadır.

**Snippet — Veri Sanitizasyonu (satır 53–58):**
```php
$full_name  = trim(htmlspecialchars($_POST['full_name'] ?? '', ENT_QUOTES, 'UTF-8'));
$email      = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
$user_type  = trim($_POST['user_type'] ?? '');
$official_id = trim($_POST['official_id'] ?? '');
$password   = $_POST['password'] ?? '';
```

**Açıklama:**
- `htmlspecialchars()` → `<`, `>`, `"`, `'` karakterlerini HTML varlıklarına dönüştürerek **XSS (Cross-Site Scripting)** saldırılarını önler.
- `filter_var(FILTER_SANITIZE_EMAIL)` → E-posta formatına uymayan karakterleri otomatik temizler.
- `trim()` → Başındaki/sonundaki boşlukları kaldırır.

**Snippet — Sunucu Tarafı Doğrulama Kontrolleri (satır 68–119):**
```php
// Boş alan kontrolü
if (empty($full_name) || empty($email) || empty($user_type) || empty($official_id) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Lütfen tüm alanları doldurun.']);
    exit;
}

// E-posta format kontrolü
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Geçerli bir e-posta adresi girin.']);
    exit;
}

// Kullanıcı tipi whitelist kontrolü
if (!in_array($user_type, ['student', 'staff'], true)) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz kullanıcı tipi seçildi.']);
    exit;
}

// 10 haneli numara regex kontrolü
if (!preg_match('/^\d{10}$/', $official_id)) {
    echo json_encode(['success' => false, 'message' => 'Numara tam olarak 10 haneli olmalıdır.']);
    exit;
}

// Şifre minimum uzunluk kontrolü
if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Şifre en az 8 karakter olmalıdır.']);
    exit;
}
```

**Açıklama:**
- Her doğrulama adımı bağımsız `if` bloğu içindedir; hata durumunda `exit` ile script sonlandırılır.
- `in_array(..., true)` → Strict type karşılaştırması ile tip dönüşüm saldırıları engellenir.
- `preg_match('/^\d{10}$/')` → Başından sonuna kadar tam 10 rakam zorunluluğu.

---

### 2.3 Şifre Güvenliği — SHA-256

**Snippet — `register_process.php` satır 143:**
```php
// Şifreyi SHA-256 ile hash'le
$hashed_password = hash('sha256', $password);
```

**Snippet — `login_process.php` satır 102–104:**
```php
// Girilen şifreyi aynı algoritmaya sokar, veritabanıyla karşılaştırır
$hashed_input = hash('sha256', $password);

if ($hashed_input !== $user['Password']) {
    echo json_encode(['success' => false, 'message' => 'E-posta veya şifre hatalı.']);
    exit;
}
```

**Açıklama:** `hash('sha256', ...)` fonksiyonu, girilen metni 64 karakterlik onaltılı (hexadecimal) bir özete dönüştürür. SHA-256 tek yönlü (geri döndürülemez) bir fonksiyon olduğundan veritabanında asla düz metin şifre saklanmamaktadır. Veritabanı şemasında `Password` sütunu `VARCHAR(64)` olarak tanımlanmıştır.

---

### 2.4 Duplicate (Çift Kayıt) Kontrolü

**Snippet — `register_process.php` satır 126–134:**
```php
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE Email = :email");
$stmt->execute([':email' => $email]);

if ($stmt->fetchColumn() > 0) {
    echo json_encode(['success' => false, 'message' => 'Bu e-posta adresi zaten kayıtlı.']);
    exit;
}
```

**Açıklama:** `COUNT(*)` sorgusu, aynı e-posta ile kayıt yapılıp yapılmadığını denetler. Prepared statement kullanımı SQL Injection saldırısını engeller.

---

### 2.5 Session Yönetimi — `login_process.php`

**Snippet — Başarılı Giriş Sonrası (satır 117–124):**
```php
// Oturum sabitleme (session fixation) saldırısına karşı yeni ID üret
session_regenerate_id(true);

$_SESSION['user_id']   = $user['User_ID'];
$_SESSION['full_name'] = $user['Full_Name'];
$_SESSION['email']     = $user['Email'];
$_SESSION['user_type'] = $user['User_Type']; // 'student' veya 'staff'
```

**Açıklama:** `session_regenerate_id(true)` çağrısı, giriş başarılı olduktan hemen sonra mevcut session ID'yi geçersiz kılıp yeni bir tane oluşturur. Bu işlem **Session Fixation** saldırısını önler. Kullanıcı bilgileri `$_SESSION` süper global dizisine kaydedilerek korumalı sayfalarda yetkilendirme denetimi için kullanılır.

---

### 2.6 Oturum Sonlandırma — `logout.php`

**Snippet (satır 19–37):**
```php
$_SESSION = [];  // Tüm session verilerini temizle

// Tarayıcıdaki session çerezini geçmiş tarihle sil
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]);
}

session_destroy(); // Sunucu tarafındaki session dosyasını yok et
```

**Açıklama:** Güvenli oturum kapatma üç adımdan oluşur: (1) `$_SESSION` dizisini boşalt, (2) `setcookie()` ile tarayıcıdaki PHPSESSID çerezini geçmiş tarihle silerek geçersiz kıl, (3) `session_destroy()` ile sunucu tarafındaki session dosyasını tamamen yok et.

---

### 2.7 MySQL Sorguları ve PDO Bağlantısı — `db_connect.php`

**Snippet (satır 38–52):**
```php
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // Hataları exception olarak fırlat
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // Sonuçları ilişkisel dizi döndür
    PDO::ATTR_EMULATE_PREPARES   => false,                   // Gerçek prepared statement kullan
];

$pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
```

**Açıklama:**
- `ERRMODE_EXCEPTION` → SQL hataları `try-catch` bloğuyla yakalanabilir hale gelir.
- `EMULATE_PREPARES => false` → PDO'nun kendi emülasyonu yerine MySQL'in native prepared statement'ı kullanılır; bu SQL Injection korumasını güçlendirir.
- `FETCH_ASSOC` → `$row['Email']` şeklinde sütun adıyla erişim sağlar, indeks karışıklığını önler.
