# Authentication Login Flow - Simple Sequence Diagram

Clean and simple sequence diagram for authentication.

## How to View
1. Open this file in VS Code
2. Press `Ctrl+Shift+V` (or right-click → Open Preview)
3. Or copy the Mermaid code to [Mermaid Live Editor](https://mermaid.live/)

---

## Login Flow (All User Types)

```mermaid
sequenceDiagram
    participant Mobile as Mobile App
    participant Auth as AuthController
    participant Driver as Driver Model
    participant Guide as Guide Model
    participant Guest as Guest Model
    participant User as User Model
    participant Tour as Tour Model
    participant AppMgmt as AppManagement
    participant Sanctum as Laravel Sanctum

    Mobile->>Auth: POST /login (email, password, user_type)
    Auth->>Auth: Validate credentials
    
    alt user_type == "driver"
        Auth->>Driver: Find driver by email
        Driver-->>Auth: Driver record
        Auth->>Auth: Verify password
        alt Valid
            Auth->>User: Get DMC info
            User-->>Auth: DMC details
            Auth->>Sanctum: Create driver token
            Sanctum-->>Auth: Token
            Auth-->>Mobile: 200 OK (driver, token, role: "driver", dmc_info)
        else Invalid
            Auth-->>Mobile: 404 Not Found
        end
        
    else user_type == "guide"
        Auth->>Guide: Find guide by email
        Guide-->>Auth: Guide record
        Auth->>Auth: Verify password
        alt Valid
            Auth->>User: Get DMC info
            User-->>Auth: DMC details
            Auth->>Sanctum: Create guide token
            Sanctum-->>Auth: Token
            Auth-->>Mobile: 200 OK (guide, token, role: "guide", dmc_info)
        else Invalid
            Auth-->>Mobile: 404 Not Found
        end
        
    else user_type == "guest"
        Auth->>Guest: Find guest by email
        Guest-->>Auth: Guest record
        Auth->>Auth: Verify password
        alt Valid
            Auth->>Guest: Decode tour_id array
            Guest-->>Auth: Tour IDs
            Auth->>Tour: Get tours
            Tour-->>Auth: Tours list
            Auth->>Auth: Calculate tour counts
            Auth->>AppMgmt: Get default images
            AppMgmt-->>Auth: Images
            Auth->>Sanctum: Create guest token
            Sanctum-->>Auth: Token
            Auth-->>Mobile: 200 OK (guest, tour_counts, token, role: "guest", default_images)
        else Invalid
            Auth-->>Mobile: 404 Not Found
        end
        
    else No matching user
        Auth-->>Mobile: 400 Bad Request
    end
```
