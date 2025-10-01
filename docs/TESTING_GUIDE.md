# Timetics Occupied Slots Addon - Testing Guide

## 🎯 **How the Addon Now Works**

### **1. Data Flow Integration**
The addon now properly integrates with Timetics' data flow:

1. **Timetics generates slots** with `status: 'available'` or `status: 'unavailable'`
2. **Our addon hooks into** `timetics_booking_slot` filter to modify slot status
3. **Slots with `status: 'unavailable'`** are changed to `status: 'occupied'` for display
4. **Frontend JavaScript** processes these slots and applies visual styling

### **2. Dynamic Slot Loading**
The addon now handles dynamic slot loading through:

- **MutationObserver**: Watches for DOM changes
- **AJAX monitoring**: Listens for Timetics API calls
- **Date change detection**: Reprocesses slots when calendar date changes
- **Periodic checking**: Fallback mechanism every 2 seconds

## 🧪 **Testing Steps**

### **Step 1: Activate the Addon**
1. Go to **WordPress Admin → Plugins**
2. Find **"Timetics Occupied Slots Addon"**
3. Click **"Activate"**

### **Step 2: Configure Settings**
1. Go to **Settings → Timetics Occupied Slots**
2. **Enable** "Show occupied slots from Google Calendar on the frontend"
3. **Customize** colors if desired

### **Step 3: Test with Real Bookings**
1. **Create a test booking** in Timetics for a specific time slot
2. **Go to the frontend** booking form
3. **Select the same date** as your test booking
4. **Look for the occupied slot** - it should appear with red background and 🚫 icon

### **Step 4: Test Google Calendar Integration (Optional)**
1. **Enable Google Calendar integration** in Timetics Settings
2. **Enable "google_calendar_overlap"** setting
3. **Add an event** to your Google Calendar for a time slot
4. **Check the frontend** - the slot should be marked as occupied

## 🔍 **Expected Console Output**

After activation, you should see:

```
Timetics Occupied Slots: Found container with selector: .tt-slot-list
Timetics Occupied Slots: Initializing...
Timetics Occupied Slots: Processed X slots
```

When slots are loaded dynamically:
```
Timetics Occupied Slots: Detected slot changes, reprocessing...
Timetics Occupied Slots: AJAX complete - processing X slots
Timetics Occupied Slots: Date changed - reprocessing slots
```

## 🎨 **Visual Results**

### **Available Slots**
- Normal appearance (default Timetics styling)
- Clickable and bookable
- Hover effects work normally

### **Occupied Slots**
- **Red background** (#ff6b6b)
- **White text** (#ffffff)
- **🚫 icon** before the time
- **Not clickable** (cursor: not-allowed)
- **Tooltip** on hover (if enabled)

## 🐛 **Troubleshooting**

### **If you see "Processed 0 slots":**
1. **Check if slots are loaded** - wait for Timetics to load the calendar
2. **Try selecting a different date** - slots load when date is selected
3. **Check browser console** for any JavaScript errors
4. **Verify the addon is enabled** in Timetics → Occupied Slots

### **If occupied slots don't appear:**
1. **Create a test booking** in Timetics admin
2. **Make sure the booking is for the same date** you're viewing
3. **Check if the slot has `status: 'unavailable'`** in Timetics
4. **Verify the addon is processing slots** (check console)

### **If styling doesn't apply:**
1. **Clear browser cache** (Ctrl+F5)
2. **Check for CSS conflicts** with your theme
3. **Verify the CSS file is loading** (check Network tab in DevTools)
4. **Try different colors** in the addon settings

## 📊 **Testing Checklist**

- [ ] Addon activates without errors
- [ ] Settings page loads correctly
- [ ] Console shows initialization messages
- [ ] Slots are detected and processed
- [ ] Occupied slots have red background
- [ ] Occupied slots show 🚫 icon
- [ ] Occupied slots are not clickable
- [ ] Tooltips work (if enabled)
- [ ] Date changes trigger reprocessing
- [ ] AJAX calls trigger reprocessing

## 🚀 **Success Indicators**

The addon is working correctly when:

1. **Console shows**: "Timetics Occupied Slots: Processed X slots" (where X > 0)
2. **Occupied slots** appear with red background and 🚫 icon
3. **Available slots** appear normal
4. **No JavaScript errors** in console
5. **Slots update** when changing dates

## 📞 **Support**

If you encounter issues:

1. **Check browser console** for error messages
2. **Note which step** fails in the testing process
3. **Provide the exact console output** you see
4. **Include any error messages** from the browser

The addon is now fully integrated with Timetics' data flow and should work seamlessly!
