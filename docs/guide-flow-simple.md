# Guide Flow - Simple Sequence Diagrams

Clean and simple sequence diagrams for guide operations.

## How to View
1. Open this file in VS Code
2. Press `Ctrl+Shift+V` (or right-click → Open Preview)
3. Or copy the Mermaid code to [Mermaid Live Editor](https://mermaid.live/)

---

## 1. Guide Login

```mermaid
sequenceDiagram
    participant Guide as Guide App
    participant Auth as AuthController
    participant GuideModel as Guide Model
    participant User as User Model
    participant Sanctum as Laravel Sanctum

    Guide->>Auth: POST /login (email, password, user_type: "guide")
    Auth->>GuideModel: Find guide by email
    GuideModel-->>Auth: Guide record
    Auth->>Auth: Verify password
    alt Valid credentials
        Auth->>User: Get DMC info
        User-->>Auth: DMC details
        Auth->>Sanctum: Create token
        Sanctum-->>Auth: Token
        Auth-->>Guide: 200 OK (guide, token, role, dmc_info)
    else Invalid credentials
        Auth-->>Guide: 404 Not Found
    end
```

---

## 2. Get Guide Jobsheets

```mermaid
sequenceDiagram
    participant Guide as Guide App
    participant Auth as AuthController
    participant Middleware as Auth Middleware
    participant Jobsheet as Jobsheet Model
    participant Order as Order Model
    participant Tour as Tour Model
    participant Guest as Guest Model

    Guide->>Auth: GET /guide/jobsheets
    Auth->>Middleware: Verify token
    Middleware-->>Auth: Authenticated guide
    Auth->>Jobsheet: Get jobsheets for today
    Jobsheet-->>Auth: Jobsheets list
    
    loop For each jobsheet
        Auth->>Order: Get order details
        Order-->>Auth: Order data
        Auth->>Tour: Get tour details
        Tour-->>Auth: Tour data
    end
    
    Auth->>Guest: Get customer info
    Guest-->>Auth: Customer data
    Auth-->>Guide: 200 OK (jobsheets, customer_info)
```

---

## 3. Update Jobsheet Status with Notifications

```mermaid
sequenceDiagram
    participant Guide as Guide App
    participant Auth as AuthController
    participant Jobsheet as Jobsheet Model
    participant Guest as Guest Model
    participant GuideModel as Guide Model
    participant GuideLang as GuideLanguage Model
    participant NotifHelper as NotificationHelper
    participant Firebase as Firebase
    participant GuestDevices as Guest Devices

    Guide->>Auth: POST /jobsheet/update-status (id, status, comments)
    Auth->>Jobsheet: Find jobsheet
    Jobsheet-->>Auth: Jobsheet record
    Auth->>Jobsheet: Update status
    Jobsheet-->>Auth: Updated
    
    Auth->>Guest: Get guest emails
    Guest-->>Auth: Email list
    
    Auth->>GuideModel: Get guide details
    GuideModel-->>Auth: Guide info
    Auth->>GuideLang: Get guide languages
    GuideLang-->>Auth: Languages list
    
    Auth->>NotifHelper: Send notification
    NotifHelper->>Firebase: Get device tokens
    Firebase-->>NotifHelper: Tokens
    NotifHelper->>Firebase: Send multicast message
    Firebase-->>GuestDevices: Push notification
    Firebase-->>NotifHelper: Report
    NotifHelper-->>Auth: Result
    
    Auth-->>Guide: 200 OK (jobsheet updated)
```

---

## 4. Get Upcoming Tours

```mermaid
sequenceDiagram
    participant Guide as Guide App
    participant Auth as AuthController
    participant Middleware as Auth Middleware
    participant Jobsheet as Jobsheet Model
    participant Order as Order Model
    participant Tour as Tour Model
    participant Guest as Guest Model

    Guide->>Auth: GET /upcoming-tours (date?)
    Auth->>Middleware: Verify token
    Middleware-->>Auth: Authenticated guide
    Auth->>Jobsheet: Get jobsheets for date
    Jobsheet-->>Auth: Jobsheets list
    
    loop For each jobsheet
        Auth->>Order: Get order details
        Order-->>Auth: Order data
        Auth->>Tour: Get tour details
        Tour-->>Auth: Tour data
    end
    
    Auth->>Guest: Get customer info
    Guest-->>Auth: Customer data
    Auth-->>Guide: 200 OK (jobsheets, customer_info)
```

---

## 5. Update Guide Password

```mermaid
sequenceDiagram
    participant Guide as Guide App
    participant Auth as AuthController
    participant Middleware as Auth Middleware
    participant GuideModel as Guide Model

    Guide->>Auth: POST /guide/update (password)
    Auth->>Middleware: Verify token
    Middleware-->>Auth: Authenticated guide
    Auth->>Auth: Hash password
    Auth->>GuideModel: Update password
    GuideModel-->>Auth: Updated
    Auth-->>Guide: 200 OK (guide updated)
```
