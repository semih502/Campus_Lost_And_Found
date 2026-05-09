## 3. Ana İşlevin Teknik Detayları

### 3.1 PDO Transaction ile Kullanıcı Kaydı — `register_process.php`

Yeni bir kullanıcı kaydında `users` (üsttip) ve `students`/`staff` (alttip) tablolarına eş zamanlı yazma zorunluluğu vardır. Proje **Supertype-Subtype (Üsttip-Alttip) veritabanı tasarımı** kullanmaktadır.

**Snippet — PDO Transaction (satır 151–197):**
```php
$pdo->beginTransaction();

// 5a) Üsttip tablosuna ekle
$stmtUser = $pdo->prepare("
    INSERT INTO users (Full_Name, Email, Password, User_Type)
    VALUES (:full_name, :email, :password, :user_type)
");
$stmtUser->execute([
    ':full_name' => $full_name,
    ':email'     => $email,
    ':password'  => $hashed_password,
    ':user_type' => $user_type
]);

$newUserId = $pdo->lastInsertId(); // Yeni kaydın otomatik artan PK'sı

// 5b) Alttip tablosuna ekle
if ($user_type === 'student') {
    $stmtSub = $pdo->prepare("
        INSERT INTO students (User_ID, Student_Number)
        VALUES (:user_id, :student_number)
    ");
    $stmtSub->execute([':user_id' => $newUserId, ':student_number' => $official_id]);
} else {
    $stmtSub = $pdo->prepare("
        INSERT INTO staff (User_ID, Staff_Number)
        VALUES (:user_id, :staff_number)
    ");
    $stmtSub->execute([':user_id' => $newUserId, ':staff_number' => $official_id]);
}

$pdo->commit(); // Her iki INSERT başarılı → onayla
```

**Açıklama:**
- `beginTransaction()` → İki INSERT işlemini atomik bir birim olarak başlatır.
- `lastInsertId()` → `users` tablosuna eklenen kaydın `User_ID`'sini döndürür; alttip tablosunda Foreign Key olarak kullanılır.
- Eğer ikinci INSERT başarısız olursa `catch` bloğundaki `rollBack()` her iki kaydı da iptal eder. Bu **veritabanı tutarlılığını** korur.

---

### 3.2 İlan Ekleme — `upload_logic.php`

**Snippet — Oturum Güvenliği (satır 46–53):**
```php
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Giriş yapmanız gerekmektedir.']);
    exit;
}
$userId = $_SESSION['user_id'];
```

**Açıklama:** İlan oluşturmak için aktif oturum zorunludur. Oturum yoksa `401 Unauthorized` HTTP kodu döndürülür ve script sonlandırılır.

**Snippet — Dosya Yükleme Güvenliği (satır 146–213):**
```php
// Gerçek MIME tipini oku (uzantıya güvenme)
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);

$allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($mimeType, $allowedMimeTypes, true)) {
    echo json_encode(['success' => false, 'message' => 'Desteklenmeyen dosya formatı.']);
    exit;
}

// 5MB boyut sınırı
$maxFileSize = 5 * 1024 * 1024;
if ($file['size'] > $maxFileSize) {
    echo json_encode(['success' => false, 'message' => 'Dosya boyutu 5MB\'ı aşamaz.']);
    exit;
}

// Benzersiz dosya adı — path traversal saldırısını önler
$newFileName = uniqid('item_', true) . '.' . $mimeToExt[$mimeType];
move_uploaded_file($file['tmp_name'], $uploadDir . $newFileName);
```

**Açıklama:**
- `finfo(FILEINFO_MIME_TYPE)` → Dosyanın gerçek içeriğini okuyarak MIME tipini belirler; uzantı değiştirilmiş zararlı dosyaları engeller.
- `uniqid('item_', true)` → Mikrosaniye bazlı benzersiz ad üretir; orijinal dosya adı hiçbir zaman kullanılmaz (path traversal koruması).
- `move_uploaded_file()` → PHP'nin güvenli dosya taşıma fonksiyonu; yalnızca HTTP upload ile gelen dosyalar için çalışır.

**Snippet — items ve item_photos Tablosuna INSERT (satır 228–256):**
```php
$pdo->beginTransaction();

$stmtItem = $pdo->prepare("
    INSERT INTO items (User_ID, Title, Description, Category_ID, Location_ID, Status_ID)
    VALUES (:user_id, :title, :description, :category_id, :location_id, :status_id)
");
$stmtItem->execute([
    ':user_id'     => $userId,
    ':title'       => $title,
    ':description' => $description,
    ':category_id' => $category,
    ':location_id' => $location,
    ':status_id'   => $statusId   // 'lost'→1, 'found'→2
]);

$newItemId = $pdo->lastInsertId();

if ($photoPath !== null) {
    $stmtPhoto = $pdo->prepare("
        INSERT INTO item_photos (Item_ID, Photo_Path)
        VALUES (:item_id, :photo_path)
    ");
    $stmtPhoto->execute([':item_id' => $newItemId, ':photo_path' => $photoPath]);
}

$pdo->commit();
```

**Açıklama:** İlan ve fotoğraf kaydı yine PDO Transaction ile atomik olarak yapılır. Fotoğraf yüklemesi başarısız olursa `rollBack()` ilan kaydını da geri alır; `unlink()` ile diskteki yüklenen dosya temizlenir.

---

### 3.3 İlan Listeleme SQL Sorgusu — `get_listings.php`

**Snippet (satır 18–38):**
```php
$stmt = $pdo->query("
    SELECT
        i.Item_ID       AS id,
        i.Title         AS title,
        i.Description   AS description,
        i.Created_At    AS created_at,
        c.Category_Name AS category,
        l.Location_Name AS location,
        s.Status_Name   AS status,
        u.Full_Name     AS owner_name,
        (SELECT Photo_Path FROM item_photos
         WHERE item_photos.Item_ID = i.Item_ID LIMIT 1) AS photo
    FROM items i
    INNER JOIN categories c ON i.Category_ID = c.Category_ID
    INNER JOIN locations  l ON i.Location_ID = l.Location_ID
    INNER JOIN statuses   s ON i.Status_ID   = s.Status_ID
    INNER JOIN users      u ON i.User_ID     = u.User_ID
    ORDER BY i.Created_At DESC
");
```

**Açıklama:**
- `INNER JOIN` ile `items` tablosu, ilgili kategori, konum, durum ve kullanıcı tablolarıyla birleştirilir; tek sorguyla zenginleştirilmiş veri seti elde edilir.
- **Correlated Subquery** → `item_photos` tablosundan ilk fotoğrafı çekmek için alt sorgu kullanılmıştır; fotoğraf yoksa `NULL` döner.
- `ORDER BY i.Created_At DESC` → En yeni ilanlar en üstte gösterilir.

---

### 3.4 İlan Detay SQL Sorgusu — `get_item_details.php`

**Snippet (satır 30–50):**
```php
$stmt = $pdo->prepare("
    SELECT
        i.Item_ID       AS id,
        i.Title         AS title,
        i.Description   AS description,
        i.Created_At    AS created_at,
        i.User_ID       AS user_id,
        c.Category_Name AS category,
        l.Location_Name AS location,
        s.Status_Name   AS status,
        u.Full_Name     AS owner_name
    FROM items i
    INNER JOIN categories c ON i.Category_ID = c.Category_ID
    INNER JOIN locations  l ON i.Location_ID = l.Location_ID
    INNER JOIN statuses   s ON i.Status_ID   = s.Status_ID
    INNER JOIN users      u ON i.User_ID     = u.User_ID
    WHERE i.Item_ID = :id
    LIMIT 1
");
$stmt->execute([':id' => $itemId]);
```

**Açıklama:** `intval($_GET['id'] ?? 0)` ile URL parametresi integer'a zorlanır; negatif veya sıfır değer `exit` ile reddedilir. `LIMIT 1` → Tek kayıt beklendiğinden performans optimizasyonu sağlar.

---

### 3.5 CSS Yapısı — Modüler Mimari

Proje CSS'i, **BEM (Block Element Modifier)** notasyonuna yakın bir modüler yapıyla organize edilmiştir:

```
css/
├── main.css               ← Tüm modülleri @import ile birleştirir
├── utils/
│   └── _variables.css     ← CSS Custom Properties (design tokens)
├── base/
│   └── _reset.css         ← Tarayıcı varsayılanlarını sıfırlar
└── components/
    ├── _navbar.css        ← Navigasyon çubuğu stilleri
    ├── _card.css          ← İlan kartı bileşeni
    ├── _filter.css        ← Arama/filtreleme paneli
    └── _buttons.css       ← Buton varyantları
```

**Snippet — CSS Custom Properties / Design Tokens (`_variables.css`):**
```css
:root {
    --color-primary:  #191970; /* Midnight Blue */
    --color-accent:   #dc2626;
    --color-bg-light: #f4f6f9;
    --color-white:    #fdfbf7;
    --color-text:     #2f3542;
    --font-heading:   'Playfair Display', serif;
    --font-body:      'Inter', sans-serif;
}

/* Dark Mode desteği */
@media (prefers-color-scheme: dark) {
    :root {
        --color-bg-light: #1a1a2e;
        --color-white:    #16213e;
        --color-text:     #e0e0e0;
    }
}
```

**Açıklama:** CSS değişkenleri tek bir dosyada tanımlanmıştır; renk paleti değiştirilmek istendiğinde yalnızca bu dosyada güncelleme yapılması yeterlidir. `@media (prefers-color-scheme: dark)` ile sistem temasına uyumlu otomatik dark mode desteği sağlanmaktadır.

**Snippet — Kart Bileşeni (`_card.css`):**
```css
.c-card {
    border: none;
    border-radius: 12px;
    transition: all 0.3s ease;   /* Hover geçiş animasyonu */
    overflow: hidden;
    position: relative;           /* Badge konumlandırma için */
}

.c-card__img {
    height: 240px;
    object-fit: cover;            /* Görseli bozmadan sığdırır */
    object-position: center;
}

.c-card__badge {
    position: absolute;
    top: 12px; left: 12px;       /* Kart üst köşesine sabitler */
    padding: 6px 12px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    z-index: 10;
}
```

**Açıklama:** BEM metodolojisiyle `.c-card` (block), `.c-card__img` (element), `.c-card__badge` (element) sınıfları birbirinden bağımsız ve yeniden kullanılabilir bileşenler olarak tanımlanmıştır.

---

## 4. AJAX Entegrasyonu

Projede AJAX iletişimi modern **Fetch API** ile sağlanmaktadır; geleneksel `XMLHttpRequest` yerine Promise tabanlı bu yaklaşım tercih edilmiştir.

### 4.1 AJAX Kullanım Noktaları

| Sayfa | PHP Endpoint | Amaç |
|-------|-------------|------|
| `04_auth.html` | `php/login_process.php` | Giriş işlemi |
| `04_auth.html` | `php/register_process.php` | Kayıt işlemi |
| `03_create-ad.html` | `php/get_form_options.php` | Select menülerini doldur |
| `03_create-ad.html` | `php/upload_logic.php` | İlan & fotoğraf yükle |
| `01_index.html` | `php/get_listings.php` | Tüm ilanları çek |
| `05_dashboard.html` | `php/check_session.php` | Oturum kontrolü |
| `05_dashboard.html` | `php/logout.php` | Çıkış işlemi |
| `02_details.html` | `php/get_item_details.php` | İlan detayı çek |
| `06_messages.html` | `php/send_message.php` | Mesaj gönder |

---

### 4.2 Giriş AJAX İsteği — `04_auth.html`

**Snippet (satır 369–397):**
```javascript
fetch('php/login_process.php', {
    method: 'POST',
    body: new FormData(form)  // Form verilerini otomatik topla
})
.then(response => response.json())  // PHP'den dönen JSON'ı parse et
.then(data => {
    if (data.success) {
        showAlert('loginAlert', data.message, true);
        setTimeout(() => {
            window.location.href = data.redirect || '05_dashboard.html';
        }, 1000);  // 1 saniye bekleyip yönlendir
    } else {
        showAlert('loginAlert', data.message, false);
        btn.disabled = false;
        btn.textContent = 'Sign In';
    }
})
.catch(error => {
    showAlert('loginAlert', 'Sunucuya bağlanılamadı.', false);
});
```

**Açıklama:**
- `e.preventDefault()` → Formun sayfa yenileyen varsayılan `submit` davranışı engellenir.
- `new FormData(form)` → Formun tüm input değerleri (ad-değer çiftleri) otomatik olarak toplanır; `multipart/form-data` formatında gönderilir.
- `.then(response => response.json())` → Sunucudan gelen ham HTTP yanıtı JSON nesnesine ayrıştırılır.
- `btn.disabled = true` → Kullanıcı butona tekrar tıklayamaz; çift gönderim (double submit) önlenir.

---

### 4.3 Dinamik Form Seçenekleri — `03_create-ad.html`

**Snippet — Sayfa Yüklendiğinde AJAX (satır 279–296):**
```javascript
document.addEventListener('DOMContentLoaded', function () {
    fetch('php/get_form_options.php')  // GET isteği
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                populateSelect('itemCategory', data.categories, 'Select a category');
                populateSelect('itemLocation', data.locations,  'Select a location');
            }
        });
});

function populateSelect(selectId, items, placeholderText) {
    const select = document.getElementById(selectId);
    select.innerHTML = '';  // Mevcut seçenekleri temizle
    items.forEach(item => {
        const option = document.createElement('option');
        option.value       = item.id;    // Category_ID / Location_ID
        option.textContent = item.name;  // Category_Name / Location_Name
        select.appendChild(option);
    });
}
```

**Açıklama:** `DOMContentLoaded` olayı sayfa HTML'i yüklendiğinde tetiklenir. `get_form_options.php`'ye yapılan GET isteğiyle kategoriler ve konumlar veritabanından çekilir; `<select>` elemanları `document.createElement('option')` ile dinamik olarak doldurulur. Bu yaklaşım sayesinde veritabanına yeni kategori eklendiğinde HTML'e dokunmaya gerek kalmaz.

**Snippet — `get_form_options.php` (satır 34–54):**
```php
$stmtCat = $pdo->query("
    SELECT Category_ID AS id, Category_Name AS name
    FROM categories
    ORDER BY Category_Name ASC
");
$categories = $stmtCat->fetchAll();

$stmtLoc = $pdo->query("
    SELECT Location_ID AS id, Location_Name AS name
    FROM locations
    ORDER BY Location_Name ASC
");
$locations = $stmtLoc->fetchAll();

echo json_encode([
    'success'    => true,
    'categories' => $categories,
    'locations'  => $locations
]);
```

**Açıklama:** İki ayrı sorgu sonucu tek JSON yanıtta birleştirilir; `AS id`, `AS name` takma adları ile frontend'in beklediği yapı (`item.id`, `item.name`) sağlanır.

---

### 4.4 Oturum Kontrolü AJAX — `05_dashboard.html`

**Snippet (satır 141–170):**
```javascript
document.addEventListener('DOMContentLoaded', function () {
    fetch('php/check_session.php')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Kullanıcı bilgilerini DOM'a yaz
                document.getElementById('navUserName').textContent  = '👤 ' + data.user.full_name;
                document.getElementById('dashUserName').textContent = data.user.full_name;
                document.getElementById('dashUserType').textContent =
                    data.user.user_type === 'student' ? 'Student' : 'Staff / Personnel';
            } else {
                // Oturum yok — login sayfasına yönlendir
                window.location.href = '04_auth.html';
            }
        })
        .catch(() => {
            window.location.href = '04_auth.html';  // Ağ hatası → güvenli taraf
        });
});
```

**Açıklama:** Dashboard sayfası yüklendiğinde `check_session.php`'ye AJAX isteği atılır. Sunucu `$_SESSION['user_id']` varlığını kontrol eder. Session aktifse kullanıcı adı ve rolü dinamik olarak navbar ve karşılama alanına yazılır. Session yoksa (401 HTTP kodu) kullanıcı otomatik olarak giriş sayfasına yönlendirilir.

---

### 4.5 İlan Yükleme — Dosyalı AJAX

**Snippet — `03_create-ad.html` (satır 347–363):**
```javascript
fetch('php/upload_logic.php', {
    method: 'POST',
    body: new FormData(form)  // Metin + dosya birlikte gönderilir
})
.then(response => {
    // 401 Unauthorized — giriş yapılmamış
    if (response.status === 401) {
        return response.json().then(data => {
            showAlert(data.message, false);
            setTimeout(() => { window.location.href = '04_auth.html'; }, 2000);
            throw new Error('UNAUTHORIZED');
        });
    }
    return response.json();
})
.then(data => {
    if (data.success) {
        showAlert(data.message, true);
        setTimeout(() => { window.location.href = data.redirect; }, 1500);
    }
});
```

**Açıklama:** `enctype="multipart/form-data"` tanımlı formda `new FormData(form)` kullanıldığında Fetch API dosyaları da otomatik olarak dahil eder. `Content-Type` header'ı **kasıtlı olarak set edilmez**; böylece `boundary` parametresiyle birlikte otomatik oluşturulur. 401 HTTP kodu ayrıca `.then()` zinciri içinde yakalanıp kullanıcıya anlamlı mesaj gösterilir.

---

### 4.6 Mesaj Gönderme AJAX — `06_messages.html` / `send_message.php`

**Snippet — `send_message.php` (satır 61–76):**
```php
$stmt = $pdo->prepare("
    INSERT INTO messages (Sender_ID, Receiver_ID, Item_ID, Content)
    VALUES (:sender_id, :receiver_id, :item_id, :content)
");
$stmt->execute([
    ':sender_id'   => $senderId,
    ':receiver_id' => $receiverId,
    ':item_id'     => $itemId > 0 ? $itemId : null,  // Null coalescing
    ':content'     => $content
]);

echo json_encode([
    'success'    => true,
    'message'    => 'Mesajınız gönderildi!',
    'message_id' => $pdo->lastInsertId()
]);
```

**Açıklama:** `messages` tablosu `Sender_ID`, `Receiver_ID`, `Item_ID` (hangi ilan hakkında) ve `Content` sütunlarından oluşur. `Item_ID` opsiyoneldir; ilan bağlamında açılan sohbetlerde doldurulur. Kullanıcının kendi kendine mesaj göndermesi `$senderId === $receiverId` koşuluyla engellenmiştir.

---

## 5. Veritabanı Şeması Özeti

```sql
-- Üsttip-Alttip (Supertype-Subtype) ilişkisi
users       (User_ID PK, Full_Name, Email, Password VARCHAR(64), User_Type ENUM)
  ├── students (User_ID PK+FK, Student_Number)
  └── staff    (User_ID PK+FK, Staff_Number)

-- Referans (Lookup) tabloları
statuses    (Status_ID PK, Status_Name)       -- 'Lost', 'Found'
categories  (Category_ID PK, Category_Name)
locations   (Location_ID PK, Location_Name)

-- Ana içerik tabloları
items       (Item_ID PK, User_ID FK, Title, Description,
             Category_ID FK, Location_ID FK, Status_ID FK, Created_At)
item_photos (Photo_ID PK, Item_ID FK, Photo_Path, Uploaded_At)
messages    (Message_ID PK, Sender_ID FK, Receiver_ID FK,
             Item_ID FK NULL, Content, Is_Read, Created_At)
```

Tüm Foreign Key ilişkilerinde `ON DELETE CASCADE` kullanılmıştır; böylece bir kullanıcı silindiğinde ilgili ilanlar ve mesajlar veritabanında yalnız (orphan) kalmaz, otomatik temizlenir. `ENGINE=InnoDB` seçimi transaction ve Foreign Key desteği için zorunludur.
