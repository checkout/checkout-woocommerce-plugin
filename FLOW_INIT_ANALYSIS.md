# Flow Payment Double Initialization – Root Cause Analysis

## 1. All Entry Points That Can Trigger Flow Initialization

| # | Entry Point | File | Trigger | Calls |
|---|-------------|------|---------|-------|
| 1 | **DOMContentLoaded (main)** | payment-session.js:4023 | Page load, Flow payment selected | `initializeFlowIfNeeded()` |
| 2 | **DOMContentLoaded (order-pay)** | payment-session.js:4168-4173 | Page load on order-pay page, Flow selected | `initializeFlowIfNeeded()` |
| 3 | **DOMContentLoaded (MutationObserver)** | payment-session.js:4109-4110 | Payment method appears in DOM after load | `initializeFlowIfNeeded()` |
| 4 | **DOMContentLoaded (periodic check)** | payment-session.js:4143-4156 | Every 2s for 30s if not initialized | `initializeFlowIfNeeded()` |
| 5 | **updated_checkout** | payment-session.js:4034-4049 | WooCommerce AJAX checkout update | `initializeFlowIfNeeded()` |
| 6 | **cko:flow-container-ready** | flow-container-ready-handler.js:119 | Container created/ready (remount path) | `initializeFlowIfNeeded()` |
| 7 | **payment_method change** | payment-session.js:3961-3997 | User switches to Flow payment | `handleFlowPaymentSelection()` → `initializeFlowIfNeeded()` |
| 8 | **Field watchers** | payment-session.js:3860-3906 | Required fields filled (debounced 500ms) | `initializeFlowIfNeeded()` |
| 9 | **Field watcher immediate check** | payment-session.js:3900-3905 | 200ms after watcher setup | `initializeFlowIfNeeded()` |

---

## 2. Timing / Order of Triggers on Page Load

```
SCRIPT LOAD ORDER (synchronous, header):
  flow-container.js
  flow-container-ready-handler.js (FlowContainerReadyHandler.init() called when payment-session loads)
  payment-session.js

DOMContentLoaded fires (all handlers run in registration order):

  ┌─────────────────────────────────────────────────────────────────────────────┐
  │ 1. flow-container.js DOMContentLoaded                                       │
  │    → addPaymentMethod() → creates #flow-container                           │
  │    → dispatchEvent('cko:flow-container-ready')  [SYNC]                      │
  └─────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
  ┌─────────────────────────────────────────────────────────────────────────────┐
  │ 2. FlowContainerReadyHandler receives cko:flow-container-ready               │
  │    → flowWasInitializedBefore = false (first load)                           │
  │    → SKIP (no remount)                                                       │
  └─────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
  ┌─────────────────────────────────────────────────────────────────────────────┐
  │ 3. payment-session.js DOMContentLoaded #1 (line 2819)                       │
  │    → FlowState.set('initialized', false)                                     │
  │    → MutationObserver setup (resets initialized when component removed)      │
  └─────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
  ┌─────────────────────────────────────────────────────────────────────────────┐
  │ 4. payment-session.js DOMContentLoaded #2 (line 4023) – MAIN HANDLER         │
  │    → Registers updated_checkout handler                                      │
  │    → If flowPayment && flowPayment.checked:                                 │
  │       → initializeFlowIfNeeded()  ← ENTRY POINT 1                           │
  │    → If !flowPayment: MutationObserver for payment method                   │
  │    → If flowPayment.checked: setupFieldWatchersForInitialization()          │
  │    → If order-pay page: initializeFlowIfNeeded()  ← ENTRY POINT 2 (duplicate!)│
  │    → Periodic check every 2s  ← ENTRY POINT 4                                │
  └─────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
  ┌─────────────────────────────────────────────────────────────────────────────┐
  │ 5. initializeFlowIfNeeded() → ckoFlow.init() → loadFlow() [ASYNC]           │
  │    → window.ckoFlowInitLock = true, FlowState.initializing = true           │
  │    → loadFlow() creates session, component, mounts (2–5 seconds)             │
  └─────────────────────────────────────────────────────────────────────────────┘

LATER (async / user / WooCommerce):

  ┌─────────────────────────────────────────────────────────────────────────────┐
  │ 6. updated_checkout (WooCommerce) – may fire on load or user action          │
  │    → flow-container.js: after 100ms → dispatchEvent('cko:flow-container-ready')│
  │    → payment-session.js: if !initialized && !initializing → init  ← ENTRY 5 │
  │    → FlowContainerReadyHandler: flowWasInitializedBefore? → maybe remount    │
  └─────────────────────────────────────────────────────────────────────────────┘

  ┌─────────────────────────────────────────────────────────────────────────────┐
  │ 7. setupFieldWatchersForInitialization()                                     │
  │    → 200ms setTimeout: initializeFlowIfNeeded()  ← ENTRY POINT 9              │
  │    → Field input/change (500ms debounce): initializeFlowIfNeeded()  ← ENTRY 8 │
  └─────────────────────────────────────────────────────────────────────────────┘
```

---

## 3. Why the Current Guards Are Failing

### 3.1 Race: `flowComponentActuallyMounted` vs SDK Timing

**FlowContainerReadyHandler** uses:

```javascript
flowWasInitializedBefore = FlowState.get('initialized') && ckoFlow.flowComponent && !flowComponentActuallyMounted
flowComponentActuallyMounted = flowComponentRoot && flowComponentRoot.isConnected
flowComponentRoot = document.querySelector('[data-testid="checkout-web-component-root"]')
```

The Checkout.com SDK may create `[data-testid="checkout-web-component-root"]` asynchronously after mount. There is a window where:

- `FlowState.initialized = true`
- `ckoFlow.flowComponent` exists
- `flowComponentRoot` is still missing or not connected

Then `flowWasInitializedBefore` becomes `true` incorrectly and triggers a remount → second init.

### 3.2 Multiple DOMContentLoaded Entry Points

On order-pay with Flow selected, `initializeFlowIfNeeded()` is called twice in the same DOMContentLoaded:

1. Line 4132: `if (flowPayment && flowPayment.checked)`
2. Line 4173: `if (orderPaySlug && flowPayment.checked)`

Guards (lock, `initializing`) usually block the second call, but both paths still run and add complexity.

### 3.3 `cko:flow-container-ready` Emitted Too Often

`flow-container.js` emits `cko:flow-container-ready` when:

1. `addPaymentMethod()` creates or finds the container (DOMContentLoaded)
2. `updated_checkout` handler (after 100ms) when the container exists

So the event fires even when nothing changed. FlowContainerReadyHandler must then distinguish “real remount needed” from “container still there,” which is fragile.

### 3.4 Debounce Gaps

- `initializeFlowIfNeeded()` uses a 2s debounce (`ckoLastInitTime`).
- Field watchers use 500ms debounce.
- `setupFieldWatchersForInitialization()` has a 200ms immediate check.

Different timings can still allow overlapping init attempts across entry points.

### 3.5 No Single Owner of Init

Init can be triggered from:

- payment-session.js (DOMContentLoaded, updated_checkout, change, watchers)
- flow-container-ready-handler.js (container-ready)
- flow-container.js (indirectly via events)

There is no single place that decides “init now” and coordinates all others.

---

## 4. Proposed Fix: Single Controller in `payment-session.js`

Use **payment-session.js** as the single controller. All other modules only signal “init might be needed”; they never call `initializeFlowIfNeeded()` directly.

### 4.1 Changes in `flow-container-ready-handler.js` (ONE file)

**Current:** Listens for `cko:flow-container-ready` and calls `initializeFlowIfNeeded()` when it thinks a remount is needed.

**Proposed:** Emit a dedicated “request init” event instead of calling `initializeFlowIfNeeded()`:

```javascript
// Instead of: initializeFlowIfNeeded();
document.dispatchEvent(new CustomEvent('cko:flow-init-requested', {
    detail: { reason: 'container-ready-remount' },
    bubbles: true
}));
```

Then in **payment-session.js**, add a single listener for `cko:flow-init-requested` that calls `initializeFlowIfNeeded()`.

### 4.2 Simpler Option: Only Change `flow-container-ready-handler.js`

Keep `initializeFlowIfNeeded()` as the single function that performs init, but make FlowContainerReadyHandler stricter so it almost never triggers a second init:

1. **Stricter remount condition:** Only remount if the component was previously mounted and is now detached. Add a short “cooldown” after a successful mount (e.g. 2 seconds) during which remount is never triggered.
2. **Avoid relying on `flowComponentRoot`:** If the SDK creates it asynchronously, use `ckoFlow.flowComponent?.isConnected` (or equivalent) instead of querying the DOM for `flowComponentRoot`.

### 4.3 Minimal One-File Fix in `flow-container-ready-handler.js`

Add a cooldown and a more reliable “actually mounted” check:

```javascript
// At top of module
const REMOUNT_COOLDOWN_MS = 2000;
let lastSuccessfulMountTime = 0;

// In the cko:flow-container-ready handler, before the flowWasInitializedBefore block:
// 1. Skip if we're within cooldown of a recent successful mount
if (Date.now() - lastSuccessfulMountTime < REMOUNT_COOLDOWN_MS) {
    ckoLogger.debug('🛡️ Skipping container-ready - within remount cooldown');
    return;
}

// 2. Use component.isConnected instead of flowComponentRoot (if SDK supports it)
const flowComponentActuallyMounted = ckoFlow.flowComponent && 
    (ckoFlow.flowComponent.isConnected ?? 
     (flowComponentRoot && flowComponentRoot.isConnected));
```

And ensure `lastSuccessfulMountTime` is set when mount succeeds (e.g. from payment-session.js via a small shared hook or event).

---

## 5. Summary

| Issue | Root Cause |
|-------|------------|
| Double init from container-ready | `flowComponentActuallyMounted` can be false right after first mount due to async SDK rendering |
| Multiple init entry points | 9+ places can call `initializeFlowIfNeeded()` |
| Fragile guards | Timing races between `initialized`, `initializing`, and DOM state |

**Recommended approach:** Centralize init in `payment-session.js`, and make FlowContainerReadyHandler either:

- Emit `cko:flow-init-requested` instead of calling `initializeFlowIfNeeded()`, or  
- Add a remount cooldown and a more reliable “mounted” check so it rarely triggers a second init.
