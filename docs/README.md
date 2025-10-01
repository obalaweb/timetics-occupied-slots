# Timetics Occupied Slots Addon

## Description

This addon enhances the Timetics booking system by displaying occupied slots from Google Calendar on the frontend. Customers can now see which time slots are unavailable before attempting to book, improving the user experience and reducing booking conflicts.

## Features

- **Visual Indicators**: Occupied slots are clearly marked with different colors and icons
- **Google Calendar Integration**: Shows slots occupied by Google Calendar events
- **Tooltip Support**: Hover tooltips explain why slots are occupied
- **Accessibility**: Full keyboard navigation and screen reader support
- **Customizable Styling**: Admin can customize colors and appearance
- **Responsive Design**: Works on all device sizes
- **Performance Optimized**: Minimal impact on page load times

## Requirements

- WordPress 5.2 or higher
- PHP 7.4 or higher
- Timetics plugin (active)
- Google Calendar integration configured in Timetics
- Google Calendar overlap setting enabled

## Installation

1. Upload the `timetics-occupied-slots-addon` folder to your `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure the settings in Timetics → Occupied Slots

## Configuration

### Prerequisites

Before this addon can work, you need to:

1. **Enable Google Calendar Integration**:
   - Go to Timetics Settings → Integrations → Google Calendar
   - Connect your Google Calendar account
   - Enable the "google_calendar_overlap" setting

2. **Configure Occupied Slots Addon**:
   - Go to Settings → Timetics Occupied Slots
   - Enable "Show occupied slots from Google Calendar on the frontend"
   - Customize colors and tooltip settings

### Settings

- **Enable Occupied Slots Display**: Toggle the main functionality
- **Show Tooltip**: Enable/disable hover tooltips
- **Occupied Slot Color**: Background color for occupied slots
- **Text Color**: Text color for occupied slots

## How It Works

### Technical Implementation

1. **Slot Detection**: The addon hooks into Timetics' slot generation process
2. **Google Calendar Check**: Queries Google Calendar for events on the selected date
3. **Overlap Detection**: Compares slot times with Google Calendar event times
4. **Visual Marking**: Applies CSS classes and styling to occupied slots
5. **User Interaction**: Prevents booking attempts on occupied slots

### Integration Points

- **Filter Hook**: `timetics_booking_slot` - Modifies individual slot data
- **Filter Hook**: `timetics_schedule_data_for_selected_date` - Processes schedule data
- **CSS Classes**: Adds `tt-slot-occupied` class to occupied slots
- **JavaScript Events**: Handles user interactions and accessibility

## Visual Design

### Occupied Slot Styling

- **Background Color**: Red (#ff6b6b) by default, customizable
- **Text Color**: White by default, customizable
- **Icon**: 🚫 emoji or ✕ symbol
- **Animation**: Subtle pulse effect (can be disabled)
- **Cursor**: Not-allowed cursor on hover

### Accessibility Features

- **ARIA Labels**: Proper labeling for screen readers
- **Keyboard Navigation**: Full keyboard support
- **High Contrast**: Support for high contrast mode
- **Reduced Motion**: Respects user's motion preferences
- **Screen Reader Text**: Hidden text for screen readers

## Browser Support

- Chrome 60+
- Firefox 55+
- Safari 12+
- Edge 79+
- Internet Explorer 11 (limited support)

## Troubleshooting

### Common Issues

1. **Occupied slots not showing**:
   - Check if Google Calendar integration is enabled
   - Verify "google_calendar_overlap" setting is enabled
   - Ensure the addon is activated and configured

2. **Styling issues**:
   - Check for CSS conflicts with your theme
   - Verify custom colors are valid hex codes
   - Clear any caching plugins

3. **JavaScript errors**:
   - Check browser console for errors
   - Ensure jQuery is loaded
   - Verify Timetics frontend scripts are loaded

### Debug Mode

Enable WordPress debug mode to see detailed error logs:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Customization

### CSS Customization

You can override the default styles by adding custom CSS:

```css
/* Custom occupied slot color */
.tt-slot-list .ant-list-item .ant-btn-block.tt-slot-occupied {
    background-color: #your-color !important;
    color: #your-text-color !important;
}

/* Disable animation */
.tt-slot-list .ant-list-item .ant-btn-block.tt-slot-occupied {
    animation: none !important;
}
```

### JavaScript Customization

You can extend the functionality by using the exposed API:

```javascript
// Mark a slot as occupied programmatically
TimeticsOccupiedSlots.markSlotAsOccupied($('#slot-button'));

// Show occupied message
TimeticsOccupiedSlots.showOccupiedMessage();

// Process all slots
TimeticsOccupiedSlots.processOccupiedSlots($('.tt-slot-list'));
```

## Performance Considerations

- **Minimal Overhead**: Only loads on pages with Timetics shortcodes
- **Efficient Queries**: Caches Google Calendar data when possible
- **Lazy Loading**: Processes slots only when needed
- **Optimized CSS**: Uses CSS variables for easy theming

## Security

- **Nonce Verification**: All admin forms use WordPress nonces
- **Input Sanitization**: All user inputs are sanitized
- **Capability Checks**: Admin functions check user capabilities
- **XSS Protection**: All outputs are properly escaped

## Changelog

### Version 1.0.0
- Initial release
- Google Calendar integration
- Visual slot indicators
- Tooltip support
- Accessibility features
- Admin settings panel

## Support

For support and feature requests, please contact the plugin author or create an issue in the plugin repository.

## License

This plugin is licensed under the GPL v2 or later.

## Credits

- Built for Timetics appointment booking system
- Integrates with Google Calendar API
- Uses WordPress best practices
- Follows accessibility guidelines (WCAG 2.1)
