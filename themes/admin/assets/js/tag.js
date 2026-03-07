class Tag {

    /**
     *
     * @param {HTMLElement} inputElement
     * @param {object} options
     */
    constructor(inputElement, options) {
        this.tagify = new Tagify (inputElement,{
            whitelist: options.whitelist || []
        });
    }
}

window.TAG = Tag;