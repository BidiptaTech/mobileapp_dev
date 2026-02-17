# Driver Flow - Simple Sequence Diagrams

Clean and simple sequence diagrams for driver operations.

## How to View
1. Open this file in VS Code
2. Press `Ctrl+Shift+V` (or right-click → Open Preview)
3. Or copy the Mermaid code to [Mermaid Live Editor](https://mermaid.live/)

---

## 1. Driver Login

```mermaid
sequenceDiagram
    participant Driver as Driver App
    participant Auth as AuthController
    participant DriverModel as Driver Model
    participant User as User Model
    participant Sanctum as Laravel Sanctum

    Driver->>Auth: POST /login (email, password, user_type: "driver")
    Auth->>DriverModel: Find driver by email
    DriverModel-->>Auth: Driver record
    Auth->>Auth: Verify password
    alt Valid credentials
        Auth->>User: Get DMC info
        User-->>Auth: DMC details
        Auth->>Sanctum: Create token
        Sanctum-->>Auth: Token
        Auth-->>Driver: 200 OK (driver, token, role, dmc_info)
    else Invalid credentials
        Auth-->>Driver: 404 Not Found
    end
```

---

## 2. Get Driver Jobsheets

```mermaid
sequenceDiagram
    participant Driver as Driver App
    participant Auth as AuthController
    participant Middleware as Auth Middleware
    participant Jobsheet as Jobsheet Model
    participant Order as Order Model
    participant Tour as Tour Model
    participant Vehicle as Vehicle Model
    participant Guest as Guest Model

    Driver->>Auth: GET /driver/jobsheets
    Auth->>Middleware: Verify token
    Middleware-->>Auth: Authenticated driver
    Auth->>Jobsheet: Get jobsheets for today
    Jobsheet-->>Auth: Jobsheets list
    
    loop For each jobsheet
        Auth->>Order: Get order details
        Order-->>Auth: Order data
        Auth->>Tour: Get tour details
        Tour-->>Auth: Tour data
        Auth->>Vehicle: Get vehicle details
        Vehicle-->>Auth: Vehicle data
    end
    
    Auth->>Guest: Get customer info
    Guest-->>Auth: Customer data
    Auth-->>Driver: 200 OK (jobsheets, customer_info)
```

---

## 3. Update Jobsheet Status with Notifications

```mermaid
sequenceDiagram
    participant Driver as Driver App
    participant Auth as AuthController
    participant Jobsheet as Jobsheet Model
    participant Guest as Guest Model
    participant DriverModel as Driver Model
    participant Vehicle as Vehicle Model
    participant Order as Order Model
    participant NotifHelper as NotificationHelper
    participant Firebase as Firebase
    participant GuestDevices as Guest Devices

    Driver->>Auth: POST /jobsheet/update-status (id, status, comments)
    Auth->>Jobsheet: Find jobsheet
    Jobsheet-->>Auth: Jobsheet record
    Auth->>Jobsheet: Update status
    Jobsheet-->>Auth: Updated
    
    Auth->>Guest: Get guest emails
    Guest-->>Auth: Email list
    
    Auth->>DriverModel: Get driver details
    DriverModel-->>Auth: Driver info
    Auth->>Vehicle: Get vehicle details
    Vehicle-->>Auth: Vehicle info
    Auth->>Order: Get order details
    Order-->>Auth: Order info
    
    Auth->>NotifHelper: Send notification
    NotifHelper->>Firebase: Get device tokens
    Firebase-->>NotifHelper: Tokens
    NotifHelper->>Firebase: Send multicast message
    Firebase-->>GuestDevices: Push notification
    Firebase-->>NotifHelper: Report
    NotifHelper-->>Auth: Result
    
    Auth-->>Driver: 200 OK (jobsheet updated)
```

---

## 4. Get Upcoming Tours

```mermaid
sequenceDiagram
    participant Driver as Driver App
    participant Auth as AuthController
    participant Middleware as Auth Middleware
    participant Jobsheet as Jobsheet Model
    participant Order as Order Model
    participant Tour as Tour Model
    participant Vehicle as Vehicle Model
    participant Guest as Guest Model

    Driver->>Auth: GET /upcoming-tours (date?)
    Auth->>Middleware: Verify token
    Middleware-->>Auth: Authenticated driver
    Auth->>Jobsheet: Get jobsheets for date
    Jobsheet-->>Auth: Jobsheets list
    
    loop For each jobsheet
        Auth->>Order: Get order details
        Order-->>Auth: Order data
        Auth->>Tour: Get tour details
        Tour-->>Auth: Tour data
        Auth->>Vehicle: Get vehicle details
        Vehicle-->>Auth: Vehicle data
    end
    
    Auth->>Guest: Get customer info
    Guest-->>Auth: Customer data
    Auth-->>Driver: 200 OK (jobsheets, customer_info)
```

---

## 5. Update Driver Password

```mermaid
sequenceDiagram
    participant Driver as Driver App
    participant Auth as AuthController
    participant Middleware as Auth Middleware
    participant DriverModel as Driver Model

    Driver->>Auth: POST /driver/update (password)
    Auth->>Middleware: Verify token
    Middleware-->>Auth: Authenticated driver
    Auth->>Auth: Hash password
    Auth->>DriverModel: Update password
    DriverModel-->>Auth: Updated
    Auth-->>Driver: 200 OK (driver updated)
```
