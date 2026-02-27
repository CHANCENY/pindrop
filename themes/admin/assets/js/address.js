function addressInit(e) {
    const addresses = document.querySelectorAll("[target-api='address']");
    addresses.forEach((address)=>{
        const country = address.querySelector("select[name$='_countryCode']");
        if (country) {
            country.addEventListener("change", async (e)=>{
                const name = country.name.split("_")[0];
                const value = e.target.value;
                const response = await fetch(`/internal/address/${value}/${name}`)
                const results = await response.json();
                if (results.status === true) {

                    const html = results.address;

                    // Parse HTML string into DOM
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, "text/html");

                    // Get address container
                    const addressContainer = doc.querySelector("[target-api='address']");

                    if (addressContainer) {
                        console.log(addressContainer)
                        const requiredFields = addressContainer.querySelector(".address-required-fields");

                        if (requiredFields) {
                            console.log(requiredFields);
                            // Example: replace existing content
                            const repl = address.querySelector(".address-required-fields")
                            if (repl) {
                              repl.innerHTML = requiredFields.innerHTML;
                            }
                        }
                    }

                }
            })
        }
    })
}

document.addEventListener('DOMContentLoaded', addressInit)