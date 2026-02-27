// Flatpickr implementation for admin theme
// Import flatpickr if using modules, otherwise ensure it's loaded via CDN

// Select all date-related inputs
const dates = document.querySelectorAll("input[type='date']");
const datetimeLocal = document.querySelectorAll("input[type='datetime-local']");
const month = document.querySelectorAll("input[type='month']");
const week = document.querySelectorAll("input[type='week']");
const time = document.querySelectorAll("input[type='time']");

// Initialize flatpickr for date inputs
if (dates.length > 0) {
    flatpickr(dates, {
        dateFormat: "Y-m-d",
        allowInput: true
    });
}

// Initialize flatpickr for datetime-local inputs
if (datetimeLocal.length > 0) {
    flatpickr(datetimeLocal, {
        enableTime: true,
        dateFormat: "Y-m-d H:i",
        allowInput: true
    });
}

// Initialize flatpickr for month inputs
if (month.length > 0) {
    flatpickr(month, {
        dateFormat: "Y-m",
        plugins: [
            new monthSelectPlugin({
                shorthand: true,
                dateFormat: "Y-m",
                altFormat: "F Y"
            })
        ]
    });
}

// Initialize flatpickr for week inputs
if (week.length > 0) {
    flatpickr(week, {
        dateFormat: "Y-\\W",
        weekNumbers: true
    });
}

// Initialize flatpickr for time inputs
if (time.length > 0) {
    flatpickr(time, {
        enableTime: true,
        noCalendar: true,
        dateFormat: "H:i",
        time_24hr: true
    });
}

// Generic initialization for any element with data-flatpickr attribute
document.addEventListener("DOMContentLoaded", function() {
    const flatpickrElements = document.querySelectorAll("[data-flatpickr]");
    
    flatpickrElements.forEach(function(element) {
        const config = element.dataset.flatpickr ? JSON.parse(element.dataset.flatpickr) : {};
        flatpickr(element, config);
    });
});