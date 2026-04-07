/*
Template Name: Velzon - Admin & Dashboard Template
Author: Themesbrand
Version: 1.2.0
Website: https://Themesbrand.com/
Contact: Themesbrand@gmail.com
File: Common Plugins Js File (Modified - Safe Version)
*/

// Helper function to load scripts safely
function loadScript(src) {
    const script = document.createElement("script");
    script.src = src;
    script.defer = true;
    document.head.appendChild(script);
}

// Load plugins if their selectors are present
if (
    document.querySelector("[toast-list]") ||
    document.querySelector("[data-choices]") ||
    document.querySelector("[data-provider]")
) {
    loadScript("https://cdn.jsdelivr.net/npm/toastify-js");
    loadScript("assets/libs/choices.js/choices.js.min.js");
    loadScript("assets/libs/flatpickr/flatpickr.min.js");
}
