# Guest Flow - Simple Sequence Diagrams

Clean and simple sequence diagrams for guest operations.

## How to View
1. Open this file in VS Code
2. Press `Ctrl+Shift+V` (or right-click → Open Preview)
3. Or copy the Mermaid code to [Mermaid Live Editor](https://mermaid.live/)

---

## 1. Guest Login

```mermaid
sequenceDiagram
    participant Guest as Guest App
    participant Auth as AuthController
    participant GuestModel as Guest Model
    participant Tour as Tour Model
    participant AppMgmt as AppManagement
    participant Sanctum as Laravel Sanctum

    Guest->>Auth: POST /login (email, password, user_type: "guest")
    Auth->>GuestModel: Find guest by email
    GuestModel-->>Auth: Guest record
    Auth->>Auth: Verify password
    alt Valid credentials
        Auth->>GuestModel: Decode tour_id array
        GuestModel-->>Auth: Tour IDs
        Auth->>Tour: Get tours
        Tour-->>Auth: Tours list
        Auth->>Auth: Calculate tour counts (past, ongoing, upcoming)
        Auth->>AppMgmt: Get default images
        AppMgmt-->>Auth: Images
        Auth->>Sanctum: Create token
        Sanctum-->>Auth: Token
        Auth-->>Guest: 200 OK (guest, tour_counts, token, role, default_images)
    else Invalid credentials
        Auth-->>Guest: 404 Not Found
    end
```

---

## 2. Get Guest Bookings

```mermaid
sequenceDiagram
    participant Guest as Guest App
    participant Auth as AuthController
    participant Middleware as Auth Middleware
    participant GuestModel as Guest Model
    participant Tour as Tour Model
    participant Order as Order Model
    participant Agent as Agent Model
    participant Agency as Agency Model
    participant Hotel as Hotel Model
    participant Attraction as Attraction Model
    participant Vehicle as Vehicle Model
    participant Driver as Driver Model
    participant Restaurant as Restaurant Model
    participant Guide as Guide Model
    participant GuideLang as GuideLanguage Model
    participant Country as Country Model

    Guest->>Auth: GET /guest/bookings (status_type)
    Auth->>Middleware: Verify token
    Middleware-->>Auth: Authenticated guest
    Auth->>GuestModel: Get tour IDs
    GuestModel-->>Auth: Tour IDs array
    Auth->>Tour: Get filtered tours
    Tour-->>Auth: Tours list
    
    loop For each tour
        Auth->>Agent: Get agent
        Agent-->>Auth: Agent record
        Auth->>Agency: Get agency
        Agency-->>Auth: Agency details
        Auth->>Order: Get orders
        Order-->>Auth: Orders list
        
        loop For each order
            alt Hotel order
                Auth->>Hotel: Get hotel details
                Hotel-->>Auth: Hotel info
            else Attraction order
                Auth->>Attraction: Get attraction details
                Attraction-->>Auth: Attraction info
            else Transport order
                Auth->>Vehicle: Get vehicle details
                Vehicle-->>Auth: Vehicle info
                Auth->>Driver: Get driver details
                Driver-->>Auth: Driver info
            else Restaurant order
                Auth->>Restaurant: Get restaurant details
                Restaurant-->>Auth: Restaurant info
            else Guide order
                Auth->>Guide: Get guide details
                Guide-->>Auth: Guide info
                Auth->>GuideLang: Get languages
                GuideLang-->>Auth: Languages
            end
        end
        
        Auth->>Country: Get country image
        Country-->>Auth: Image
    end
    
    Auth-->>Guest: 200 OK (tours with orders)
```

---

## 3. Update Guest Password

```mermaid
sequenceDiagram
    participant Guest as Guest App
    participant Auth as AuthController
    participant Middleware as Auth Middleware
    participant GuestModel as Guest Model

    Guest->>Auth: POST /guest/update (password)
    Auth->>Middleware: Verify token
    Middleware-->>Auth: Authenticated guest
    Auth->>Auth: Hash password
    Auth->>GuestModel: Update password
    GuestModel-->>Auth: Updated
    Auth-->>Guest: 200 OK (guest updated)
```

---

## 4. Share Contact Status Update

```mermaid
sequenceDiagram
    participant Guest as Guest App
    participant Auth as AuthController
    participant GuestModel as Guest Model

    Guest->>Auth: POST /guest/share-contact (email, guest_id, share_status)
    Auth->>GuestModel: Find guest
    GuestModel-->>Auth: Guest record
    alt Guest found
        Auth->>GuestModel: Update share_contact
        GuestModel-->>Auth: Updated
        Auth-->>Guest: 200 OK (guest updated)
    else Guest not found
        Auth-->>Guest: 404 Not Found
    end
```

---

## 5. Explore Cities

```mermaid
sequenceDiagram
    participant Guest as Guest App
    participant Auth as AuthController
    participant City as City Model
    participant CityExp as CityExploration Model

    Guest->>Auth: GET /explore-cities (country, city)
    Auth->>City: Find city
    City-->>Auth: City record
    Auth->>CityExp: Get city exploration
    CityExp-->>Auth: Exploration data
    alt Found
        Auth-->>Guest: 200 OK (city_exploration)
    else Not found
        Auth-->>Guest: 404 Not Found
    end
```
