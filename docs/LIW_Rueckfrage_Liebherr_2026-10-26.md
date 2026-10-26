# Rückfrage an Liebherr – Dealer Interface (Sales API v3), Testumgebung

**Betreff:** Dealer Interface Test – Kunde angelegt, Ansprechpartner liefert 401 „Partner not associated to requested entity"

Sehr geehrte Damen und Herren,

wir binden die Dealer Interface API (Sales API v3) für Tradler Baumaschinen GmbH an und haben in der Testumgebung
(`https://livision.azure-api.net/crm/v3`) erste Aufrufe durchgeführt. Lesende Endpunkte funktionieren
(`/product-list`, `/tab-text`, `/sales-orders`, `/customer-quotation-list`). Beim Anlegen von Kunden und Ansprechpartnern
kommen wir nicht weiter und bitten um Ihre Hilfe.

**Was funktioniert**

- `POST /customer` → HTTP 200. Angelegt wurden zwei klar gekennzeichnete Testkunden:
  - `LivisionCustomerId 2143795932`, Name1 „Sunnyside One", `SalesAccountNumber` 450324
  - `LivisionCustomerId 2143795933`, Name1 „Sunnyside Two", `SalesAccountNumber` 020
  Beide Antworten enthalten `SalesArea "LHB"`.

**Was fehlschlägt**

- `POST /contact` mit `LivisionCustomerId` 2143795932 bzw. 2143795933 → HTTP 401,
  `{"status":{"code":"401","message":"Partner not associated to requested entity.","source":"[interface].[Dealer_CreateContact]"}}`
- `GET /livision-url?LivisionCustomerId=2143795933` → HTTP 401,
  `"Customer doesn't exist or is not associated to this partner"` (`[interface].[Dealer_GetLivisionURL]`)

Alle Aufrufe tragen den Test-Subscription-Key sowie den Header `UserEmail: <unsere registrierte Adresse>`.

**Unsere Fragen**

1. Wie wird in der Testumgebung die Zuordnung zwischen unserem Partner (Subscription-Key / `UserEmail`) und den von uns
   angelegten Kunden hergestellt? Muss unser Partner im Testsystem noch freigeschaltet oder einem Verkaufsgebiet
   zugeordnet werden?
2. Welcher Wert ist in `SalesAccountNumber` bei `POST /customer` erwartet – unsere MyLiebherr-Kundennummer (450324,
   Liebherr-Hydraulikbagger) oder der `SalesOrgCustomerCode` (020, aus `GET /sales-orders`)? Beide Werte wurden beim
   Anlegen akzeptiert.
3. Ist `UserEmail` an einen bestimmten Livision-Benutzer gebunden, dem die neuen Kunden zugeordnet werden?

**Nachtrag 26.10.2026 (zweite Anmeldekennung)**

Mit einer weiteren `UserEmail` (Subscription-Key unverändert) antwortet die Testumgebung:
- `POST /customer` → HTTP 400 `{"status":{"code":"400","message":"Parameter User-Email unknown","source":"interface.Dealer_CreateCustomer"}}`
- `GET /livision-url` → HTTP 401 `"Error: UPN not foud. Needs a valid eMail or User"`
Lesende Endpunkte ohne Benutzerbindung (`/product-list`, `/tab-text`, `/opportunity?customerName=…`) funktionieren mit beiden Adressen.
Frage 4: Welche E-Mail-Adresse ist in Livision als Benutzer (UPN) für Tradler angelegt, und wie wird dieser Benutzer dem
Händlerkonto zugeordnet, sodass `POST /contact` und `GET /livision-url` für die angelegten Kunden funktionieren?

**Nachtrag 26.10.2026, 19:20 – gelöst mit dritter Kennung**

Mit einer `UserEmail` der Domäne tradler.com funktionieren `POST/PUT /customer`, `POST/PUT /contact` und `GET /livision-url`
(Kunde 2143795934, Kontakt 2144227395). Fragen 1, 3 und 4 sind damit beantwortet: `UserEmail` muss ein in Livision
angelegter Benutzer des Händlers sein. Offen bleibt Frage 2 (`SalesAccountNumber`: 450324 akzeptiert, `SalesArea` der
Antwort ist jetzt „020“). Bitte löschen Sie die verwaisten Testkunden 2143795932 „Sunnyside One“ und 2143795933
„Sunnyside Two“ (unter einem anderen Benutzer angelegt, dem Partner nicht zugeordnet).

**Weitere Hinweise**

- `GET /opportunity` ohne Filterparameter antwortet mit HTTP 500; mit Filter (z. B. `customerName`) mit 200.
- `PUT /opportunity` hat in der Spezifikation keinen Request-Body; `GET /sales-orders` führt den Parameter `oderDateFrom`
  (vermutlich `orderDateFrom`). Könnten Sie beides bestätigen?
- Die beiden Testkunden „Sunnyside One/Two" dürfen jederzeit gelöscht werden.

Vielen Dank und freundliche Grüße

Joseph White
GoHeal – Solution Provider für Tradler Baumaschinen GmbH
