# DMC App Sequence Diagrams

This directory contains detailed sequence diagrams for the DMC App project, showing how `AuthController` and `NotificationController` interact with various models and services.

## 📋 Available Diagrams

### 🔐 Authentication
- **[Authentication Login Flow (Simple)](./auth-login-simple.md)** - Clean and simple login flow for all user types

### 🚗 Driver Flows
- **[Driver Flow (Simple)](./driver-flow-simple.md)** - Clean and simple driver operations (5 separate diagrams)
- **[Complete Driver Flow](./driver-complete-flow.md)** - Master sequence diagram showing all driver operations in one flow

### 🧭 Guide Flows
- **[Guide Flow (Simple)](./guide-flow-simple.md)** - Clean and simple guide operations (5 separate diagrams)
- **[Complete Guide Flow](./guide-complete-flow.md)** - Master sequence diagram showing all guide operations in one flow

### 👤 Guest Flows
- **[Guest Flow (Simple)](./guest-flow-simple.md)** - Clean and simple guest operations (5 separate diagrams)
- **[Complete Guest Flow](./guest-complete-flow.md)** - Master sequence diagram showing all guest operations in one flow

> **💡 Tip**: 
> - Use **Simple** versions for clean, easy-to-read individual operation diagrams
> - Use **Complete Flow** diagrams for a comprehensive view of each user type's entire workflow from login to all operations

## 🎨 How to View These Diagrams

### Prerequisites
- VS Code with **Markdown Preview Mermaid Support** extension installed

### Steps
1. Open any `.md` file in this directory
2. Press `Ctrl+Shift+V` (Windows/Linux) or `Cmd+Shift+V` (Mac)
   - Or right-click → **Open Preview** / **Open Preview to the Side**
3. The Mermaid sequence diagrams will render automatically with colors and formatting

### Alternative: View in Browser
1. Install a Markdown viewer that supports Mermaid (e.g., Markdown Preview Enhanced)
2. Or use online tools like [Mermaid Live Editor](https://mermaid.live/)

## 📝 Diagram Features

- **Clean and simple**: Easy-to-read sequence diagrams without complex theming
- **Participant clarity**: Clear participant names and interactions
- **Error handling**: Shows all error paths and responses
- **Database interactions**: Clear model-to-database relationships
- **Notification flows**: Firebase notification delivery flows
- **Complete workflows**: Master diagrams show entire user journeys

## 🔄 Key Components Shown

- **AuthController**: Main authentication and user management
- **NotificationController**: Direct notification sending
- **NotificationHelper**: Helper class for sending notifications to guests
- **Firebase**: Realtime Database and Cloud Messaging
- **Laravel Sanctum**: Token-based authentication
- **Models**: Driver, Guide, Guest, Jobsheet, Tour, Order, Vehicle, etc.

## 📚 Related Files

- `app/Http/Controllers/AuthController.php` - Main authentication controller
- `app/Http/Controllers/NotificationController.php` - Notification controller
- `app/Helpers/NotificationHelper.php` - Notification helper class
