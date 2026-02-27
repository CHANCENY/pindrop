document.addEventListener('DOMContentLoaded',async ()=>{
    const langSelectField = document.getElementById('languageSupport');
    if (langSelectField) {
        langSelectField.addEventListener('change',async (e)=>{
            const value = e.target.value;
            const response = await fetch('/internal/lang/switch',{
                method: "POST",
                body: JSON.stringify({lang: value, path: window.location.pathname})
            });
            const results = await response.json()
            alert("Switch language to "+ results.lang);
            window.location.href = results.path;
        })
    }

})
function googleTranslateElementInit() {
    // Fetch the default language from your backend
    fetch('/internal/lang/support/default')
        .then(response => response.json())
        .then(lang => {
            // Initialize Google Translate
            new google.translate.TranslateElement(
                {
                    pageLanguage: 'en',
                    layout: google.translate.TranslateElement.InlineLayout.HORIZONTAL
                },
                'google_translate_element'
            );
            const googleSelectField = document.querySelector("#google_translate_element select");
            if (googleSelectField) {
                googleSelectField.value = lang.lang; // e.g., 'ny'

                // Dispatch native change event
                googleSelectField.dispatchEvent(new Event('change', { bubbles: true }));
            }
        })
        .catch(err => {
            console.error('Failed to fetch default language:', err);
        });
}
