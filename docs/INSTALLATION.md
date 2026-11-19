# UdharPay Installation Guide — Android Mobile (AWebServer + phpMyAdmin) [हिन्दी गाइड]

यह गाइड विशेष रूप से उन beginners के लिए बनाई गई है जो **Android Phone** पर **AWebServer (Apache + PHP + MariaDB + phpMyAdmin)** का उपयोग करके **UdharPay** प्रोजेक्ट को चलाना और टेस्ट करना चाहते हैं।

---

## जरूरी ऐप्स (Android Phone में इंस्टॉल करें)
1. **AWebServer** (Play Store से — Apache + PHP 8.3+ + MariaDB के लिए)
2. **ZArchiver** (ZIP फाइल को सही तरीके से extract करने के लिए)
3. **QuickEdit / Acode** (`.env` और config फाइल एडिट करने के लिए)
4. **Google Chrome** (phpMyAdmin और UdharPay चलाने के लिए)

---

## स्टेप 1: ZIP फाइल को Extract कैसे करें
1. `UdharPay_Complete_Project.zip` फाइल डाउनलोड करें (यह आपके `Download` फोल्डर में सेव होगी)।
2. **ZArchiver** ऐप खोलें और `Download` फोल्डर में जाएँ।
3. `UdharPay_Complete_Project.zip` पर टैप करें और **Extract here** या **Extract to...** चुनें।
4. Extract होने के बाद आपको `UdharPay` नाम का फोल्डर मिलेगा, जिसमें 4 मुख्य फोल्डर होंगे:
   - `frontend/` (React वेब ऐप)
   - `backend/` (Core PHP 8.3+ REST API)
   - `database/` (`database.sql` और `demo_data.sql`)
   - `docs/` (यह गाइड और डॉक्यूमेंटेशन)

---

## स्टेप 2: Frontend और Backend फाइल्स कहाँ रखें (AWebServer Document Root)
1. **AWebServer** ऐप खोलें।
2. **Web Server** सेक्शन में **Document Root** चेक करें (आमतौर पर `/storage/emulated/0/htdocs` होता है)।
3. ZArchiver से पूरे `UdharPay` फोल्डर को कॉपी या मूव करके `htdocs` के अंदर रख दें:
   - Backend path: `/storage/emulated/0/htdocs/UdharPay/backend`
   - Frontend path: `/storage/emulated/0/htdocs/UdharPay/frontend`
4. **सुरक्षा नियम:** `.env` फाइल को public folder से बाहर रखना सबसे सुरक्षित है, इसलिए आप `.env.example` को कॉपी करके `htdocs/UdharPay/.env` नाम से सेव करें (हमारा `backend/config/env.php` अपने आप पैरेंट फोल्डर की `.env` फाइल पढ़ लेता है)।

---

## स्टेप 3: PHP 8.3+ कॉन्फ़िगरेशन (AWebServer में)
1. AWebServer में **Apache / Web Server** को **Start** करें (डिफ़ॉल्ट पोर्ट आमतौर पर `8080` होता है)।
2. सुनिश्चित करें कि PHP extensions (`pdo_mysql`, `json`, `mbstring`, `fileinfo`, `openssl`) चालू हैं (AWebServer में ये पहले से ऑन होते हैं)।
3. जांच करने के लिए Chrome में खोलें:
   `http://localhost:8080/UdharPay/backend/api/health`
   यदि JSON response दिखे, तो PHP backend सही चल रहा है।

---

## स्टेप 4: MariaDB डेटाबेस बनाना और `database.sql` Import करना (phpMyAdmin)
1. AWebServer ऐप में **MariaDB (SQL)** सर्वर को **Start** करें।
2. AWebServer में **phpMyAdmin** बटन पर टैप करें या Chrome में खोलें: `http://localhost:8080/phpmyadmin`
3. लॉगिन करें:
   - Username: `root`
   - Password: *(खाली छोड़ दें, या जो पासवर्ड आपने AWebServer में सेट किया है वह डालें)*
4. ऊपर **Databases** टैब पर क्लिक करें।
5. नया डेटाबेस बनाएँ: नाम लिखें `udharpay_db` और Collation चुनें `utf8mb4_unicode_ci`, फिर **Create** दबाएँ।
6. बाएँ साइडबार से `udharpay_db` चुनें और ऊपर **Import** टैब पर क्लिक करें।
7. **Choose File** दबाकर `UdharPay/database/database.sql` चुनें और नीचे **Import / Go** दबाएँ।
   - इससे सभी 34 टेबल्स, फॉरेन कीज़, इंडेक्स और append-only ledger triggers बन जाएँगे।
8. डेमो अकाउंट्स और सैंपल डेटा के लिए दोबारा **Import** पर जाएँ और `UdharPay/database/demo_data.sql` को import करें।

---

## स्टेप 5: Database Credentials कॉन्फ़िगर करना (`.env`)
1. `UdharPay/.env.example` फाइल को कॉपी करके `UdharPay/.env` नाम दें।
2. **Acode / QuickEdit** में `UdharPay/.env` खोलें और ये मान चेक/अपडेट करें:
   ```env
   APP_ENV=development
   APP_URL=http://localhost:8080/UdharPay/backend
   FRONTEND_ORIGIN=http://localhost:5173

   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=udharpay_db
   DB_USER=root
   DB_PASS=

   PAYMENT_MODE=demo
   ```
3. फाइल सेव करें। अब दोबारा Chrome में `http://localhost:8080/UdharPay/backend/api/health` खोलें — अब `"connected": true` और `"tables_count": 34` दिखाई देगा।

---

## स्टेप 6: React Frontend को PHP API से जोड़ना और चलाना
### विकल्प A (Termux / Laptop पर Dev Mode):
1. `UdharPay/frontend/.env` फाइल बनाएँ और लिखें:
   ```env
   VITE_API_BASE_URL=http://localhost:8080/UdharPay/backend
   ```
2. टर्मिनल (Termux या PC) में कमांड चलाएँ:
   ```bash
   cd UdharPay/frontend
   npm install
   npm run dev
   ```
3. ब्राउज़र में `http://localhost:5173` खोलें।

### विकल्प B (Static Production Build — सीधे Apache / AWebServer पर चलाएँ):
1. `npm run build` चलाकर `frontend/dist` फोल्डर बनाएँ।
2. `dist/` के अंदर की फाइलों को `htdocs/UdharPay/app/` में रख दें।
3. Chrome में सीधे खोलें: `http://localhost:8080/UdharPay/app/`

---

## स्टेप 7: एप्लिकेशन को टेस्ट कैसे करें (DEMO Accounts)
सभी डेमो अकाउंट्स का पासवर्ड: **`Demo@1234`**

| रोल (Role) | मोबाइल नंबर | ईमेल |
|---|---|---|
| Super Admin (DEMO) | `9800000001` | `admin@udharpay.demo` |
| Merchant (DEMO) | `9800000002` | `merchant@udharpay.demo` |
| Customer (DEMO) | `9800000003` | `customer@udharpay.demo` |
| Support (DEMO) | `9800000004` | `support@udharpay.demo` |
| Finance (DEMO) | `9800000005` | `finance@udharpay.demo` |
| Manager (DEMO) | `9800000006` | `manager@udharpay.demo` |

### टेस्टिंग चेकलिस्ट:
1. **Merchant** (`9800000002`) से लॉगिन करें -> नया Credit Bill बनाएँ -> बिल `pending_customer_approval` में दिखेगा।
2. **Customer** (`9800000003`) से लॉगिन करें -> Shop QR देखें (QR स्कैन करने से अपने आप कोई उधार नहीं बनेगा) -> Pending Bill को **Accept** या **Reject** करें।
3. Accept करने पर Append-Only Ledger में डेबिट एंट्री दिखेगी।
4. अब **Partial** या **Full DEMO Repayment** करें और रसीद डाउनलोड/प्रिंट करें।
5. **AutoPay Mandate** सेक्शन में जाकर Mandate कैंसल करके देखें — बकाया उधार सुरक्षित रहेगा और मर्चेंट को नोटिफिकेशन जाएगा।
