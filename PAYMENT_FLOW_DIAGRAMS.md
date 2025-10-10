# Payment Flow Diagrams

## 1. Normal Card Payment Flow

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   User Fills    │    │  Selects Flow    │    │  Flow Component │
│  Checkout Form  │───►│ Payment Method   │───►│   Initializes   │
└─────────────────┘    └──────────────────┘    └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │  User Enters    │
         │                       │              │  Card Details   │
         │                       │              └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │ Payment Session │
         │                       │              │   API Call      │
         │                       │              └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │ Payment         │
         │                       │              │ Processing      │
         │                       │              └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │ Redirect to     │
         │                       │              │ Thank You Page  │
         │                       │              └─────────────────┘
```

## 2. MOTO Order Flow (Updated with 3DS Support)

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│  Admin Creates  │    │  Order Pay Page  │    │  MOTO Detection │
│     Order       │───►│    Loads         │───►│   (Card Only)   │
└─────────────────┘    └──────────────────┘    └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │ Hide Saved      │
         │                       │              │ Cards & Save    │
         │                       │              │ Checkbox        │
         │                       │              └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │ Auto-populate   │
         │                       │              │ Cardholder Name │
         │                       │              └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │ Flow Component  │
         │                       │              │ (Card Only)     │
         │                       │              └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │ Payment Type:   │
         │                       │              │ MOTO            │
         │                       │              └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │ 3DS Challenge   │
         │                       │              │ (If Required)   │
         │                       │              └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │ Redirect to     │
         │                       │              │ Order-Received  │
         │                       │              │ Page            │
         │                       │              └─────────────────┘
```

## 3. Saved Card Flow

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│  User Logged In │    │  Check Migration │    │  Display Saved  │
│                 │───►│  Status          │───►│     Cards       │
└─────────────────┘    └──────────────────┘    └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │ User Selects    │
         │                       │              │ Saved Card      │
         │                       │              └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │ Token Payment   │
         │                       │              │ Processing      │
         │                       │              └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │ Redirect to     │
         │                       │              │ Thank You Page  │
         │                       │              └─────────────────┘
```

## 4. 3DS Authentication Flow (Updated for Order-Pay)

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│  Payment        │    │  3DS Challenge   │    │  User           │
│  Initiated      │───►│  Required        │───►│  Authenticates  │
└─────────────────┘    └──────────────────┘    └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │ 3DS Response    │
         │                       │              │ Sent to         │
         │                       │              │ Checkout.com    │
         │                       │              └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │ Webhook         │
         │                       │              │ Notification    │
         │                       │              └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │ Order Status    │
         │                       │              │ Updated         │
         │                       │              └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │ Submit Correct  │
         │                       │              │ Form Based on   │
         │                       │              │ Page Type       │
         │                       │              └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │ Redirect to     │
         │                       │              │ Order-Received  │
         │                       │              │ (Order-Pay) or  │
         │                       │              │ Thank You Page  │
         │                       │              │ (Checkout)      │
         │                       │              └─────────────────┘
```

## 5. Webhook Processing Flow

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│  Checkout.com   │    │  WooCommerce     │    │  Verify         │
│  Sends Webhook  │───►│  Receives        │───►│  Signature      │
└─────────────────┘    └──────────────────┘    └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │ Find Order by   │
         │                       │              │ Payment ID      │
         │                       │              └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │ Process Event   │
         │                       │              │ Type            │
         │                       │              └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │ Update Order    │
         │                       │              │ Status          │
         │                       │              └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │ Send Response   │
         │                       │              │ to Checkout.com │
         │                       │              └─────────────────┘
```

## 6. Google Pay Flow

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│  Google Pay     │    │  User Clicks     │    │  Google Pay     │
│  Button Shows   │───►│  Google Pay      │───►│  Sheet Opens    │
└─────────────────┘    └──────────────────┘    └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │ User Selects    │
         │                       │              │ Payment Method  │
         │                       │              └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │ Google Pay      │
         │                       │              │ Token Generated │
         │                       │              └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │ Token Sent to   │
         │                       │              │ Checkout.com    │
         │                       │              └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │ Payment         │
         │                       │              │ Processed       │
         │                       │              └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │ Redirect to     │
         │                       │              │ Thank You Page  │
         │                       │              └─────────────────┘
```

## 7. PayPal Flow

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│  PayPal Button  │    │  User Clicks     │    │  PayPal Popup   │
│  Shows          │───►│  PayPal          │───►│  Opens          │
└─────────────────┘    └──────────────────┘    └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │ User Logs In    │
         │                       │              │ & Confirms      │
         │                       │              └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │ PayPal Returns  │
         │                       │              │ with Token      │
         │                       │              └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │ Token Sent to   │
         │                       │              │ Checkout.com    │
         │                       │              └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │ Payment         │
         │                       │              │ Processed       │
         │                       │              └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │ Redirect to     │
         │                       │              │ Thank You Page  │
         │                       │              └─────────────────┘
```

## 8. Error Handling Flow

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│  Error Occurs   │    │  Error Detected  │    │  Log Error      │
│  in Payment     │───►│  by System       │───►│  Details        │
└─────────────────┘    └──────────────────┘    └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │ Determine Error │
         │                       │              │ Type            │
         │                       │              └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │ Show User       │
         │                       │              │ Friendly        │
         │                       │              │ Error Message   │
         │                       │              └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │ Allow User to   │
         │                       │              │ Retry Payment   │
         │                       │              └─────────────────┘
```

---

## Key Decision Points

### 1. Payment Method Selection
```
User Input → Check Available Methods → Display Options → User Selects → Process
```

### 2. MOTO vs Regular Order
```
Order Source → Check is_created_via('admin') → Apply MOTO Logic → Process
```

### 3. Saved Card vs New Card
```
User Selection → Check Token ID → Use Token or New Card → Process
```

### 4. 3DS Required
```
Payment Request → Check 3DS Requirements → Challenge or Direct → Complete
```

### 5. Webhook vs Direct Redirect
```
Payment Status → Check if Webhook Needed → Process Accordingly → Complete
```

## 9. Cardholder Name Auto-Population Flow

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│  Order-Pay Page │    │  Check if Card   │    │  Extract Order  │
│  Loads          │───►│  Holder Name     │───►│  Data from      │
│                 │    │  Enabled         │    │  Attributes     │
└─────────────────┘    └──────────────────┘    └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │ Parse Billing   │
         │                       │              │ Address Data    │
         │                       │              └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │ Combine First   │
         │                       │              │ & Last Name     │
         │                       │              └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │ Set Cardholder  │
         │                       │              │ Name in Flow    │
         │                       │              │ Component       │
         │                       │              └─────────────────┘
```

## 10. Order-Pay 3DS Redirect Flow

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│  3DS Challenge  │    │  Extract Order   │    │  Construct      │
│  Completed      │───►│  ID from URL     │───►│  Order-Received │
│                 │    │  Path            │    │  URL            │
└─────────────────┘    └──────────────────┘    └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │ Set Success &   │
         │                       │              │ Failure URLs    │
         │                       │              └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │ Submit Order-Pay │
         │                       │              │ Form (not       │
         │                       │              │ Checkout Form)  │
         │                       │              └─────────────────┘
         │                       │                       │
         │                       │                       ▼
         │                       │              ┌─────────────────┐
         │                       │              │ Redirect to     │
         │                       │              │ Order-Received  │
         │                       │              │ Page            │
         │                       │              └─────────────────┘
```

---

**Note**: These diagrams represent the high-level flow. Actual implementation includes additional error handling, validation, and edge cases not shown here for clarity.

**Recent Updates (January 10, 2025)**:
- Order-pay 3DS flow now properly redirects to order-received page
- Cardholder name auto-population works on order-pay pages
- Proper form submission based on page type (order-pay vs checkout)
- Order ID extraction from URL path instead of query parameters
