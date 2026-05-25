import {
  ClassicEditor,
  Essentials,
  Paragraph,
  Heading,
  Bold,
  Italic,
  Font,
  List,
  ListProperties,
  Image,
  ImageToolbar,
  ImageUpload,
  ImageInsert,
  Base64UploadAdapter,
  SourceEditing,
  GeneralHtmlSupport,
  FullPage,
} from "/themes/admin/assets/ckeditor5/ckeditor5.js";
import { Autocomplete } from "./autocomplete.js";
import "../tagify-4.36.0/dist/tagify.js";
import "../flatpickr/flatpickr.min.js";
import { initFileInputs } from "./upload.js";
import "./admin.js"

class Behaviour {
  /**
   * @param {HTMLElement} context
   */
  constructor(context) {
    this.context = context;
    this.autoComplete = [];
    this.modalClickable = [];
    this.addressFields = [];
    this.tagFields = [];

    this.init();
  }

  init() {
    // Init the address fields
    this.addressFields = [];
    this.tagFields = [];
    this.autoComplete = [];

    const addresses = this.context.querySelectorAll("[target-api='address']");
    addresses.forEach(async (address) => {
      const country = address.querySelector("select");
      if (country) {
        const response = await fetch("/internal/countries");
        const countries = await response.json();
        country.appendChild(this.context.createElement("option"));
        Object.keys(countries).forEach((code) => {
          const option = document.createElement("option");
          option.value = code;
          option.textContent = countries[code];
          country.appendChild(option);
        });

        this.addressFields.push(country);
        this.addressCountryEventAttachment(country);
      }
    });

    // init the autocomplete fields.
    const autocomplete = this.context.querySelectorAll("[autocomplete='true']");
    if (autocomplete.length > 0) {
      const settings = Array.from(autocomplete).map((element) => {
        const object = JSON.parse(element.textContent);
        if (object?.fieldId && object?.source && object?.name) {
          const autoObj = {
            fieldId: object.fieldId,
            source: object.source,
            name: object.name,
            loadingText: object?.loadingText ?? "Searching..",
            noResultsText: object?.noResultsText ?? "No results",
            noResultsText: object?.searchFields ?? ["title", "id"],
            valueField: object?.valueField ?? "value",
            displayField: object?.displayField ?? "label",
            limit: object?.limit ?? 10,
            minQueryLength: object?.minQueryLength ?? 2,
            placeholder:
              object?.placeholder ?? element.placeholder ?? "Search..",
            onSelect: object?.onSelect ?? null,
          };
          return autoObj;
        }
        return null;
      });

      settings.forEach((item) => {
        if (item !== null) {
          this.autoComplete.push(new Autocomplete(item));
        }
      });
      autocomplete.forEach((item) => item.remove());
    }

    // init the tags
    const tags = this.context.querySelectorAll("[tags='true']");
    if (tags.length > 0) {
      const tagSettings = Array.from(tags).map((element) => {
        const object = JSON.parse(element.textContent);
        if (object?.id && object?.options?.whitelist) {
          const obj = {
            id: object.id,
            options: object.options,
          };
          return obj;
        }
        return null;
      });

      tagSettings.forEach((setting) => {
        console.log(setting);
        if (setting !== null) {
          const element = this.context.querySelector("#" + setting.id);
          if (element) {
            const cloneGenresElem = element.cloneNode(true);
            element.replaceWith(cloneGenresElem);
            this.tagFields.push(new Tagify(cloneGenresElem, setting.options));
          }
        }
      });

      tags.forEach((item) => item.remove());
    }

    // init the ckeditor
    const ckEditorTextarea = this.context.querySelectorAll("textarea.editor");
    if (ckEditorTextarea) {
      ckEditorTextarea.forEach((element) => {
        this.initCkEditor(element)
          .then((editor) => {
            window.editor = editor;
            window.editors[element.id] = editor;
          })
          .catch((error) => {
            console.error(error);
          });
      });
    }

    // init date fields
    // Select all date-related inputs
    const dates = this.context.querySelectorAll("input[type='date']");
    const datetimeLocal = this.context.querySelectorAll(
      "input[type='datetime-local']",
    );
    const month = this.context.querySelectorAll("input[type='month']");
    const week = this.context.querySelectorAll("input[type='week']");
    const time = this.context.querySelectorAll("input[type='time']");

    // Initialize flatpickr for date inputs
    if (dates.length > 0) {
      flatpickr(dates, {
        dateFormat: "Y-m-d",
        allowInput: true,
      });
    }

    // Initialize flatpickr for datetime-local inputs
    if (datetimeLocal.length > 0) {
      flatpickr(datetimeLocal, {
        enableTime: true,
        dateFormat: "Y-m-d H:i",
        allowInput: true,
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
            altFormat: "F Y",
          }),
        ],
      });
    }

    // Initialize flatpickr for week inputs
    if (week.length > 0) {
      flatpickr(week, {
        dateFormat: "Y-\\W",
        weekNumbers: true,
      });
    }

    // Initialize flatpickr for time inputs
    if (time.length > 0) {
      flatpickr(time, {
        enableTime: true,
        noCalendar: true,
        dateFormat: "H:i",
        time_24hr: true,
      });
    }

    const flatpickrElements = this.context.querySelectorAll("[data-flatpickr]");

    flatpickrElements.forEach(function (element) {
      const config = element.dataset.flatpickr
        ? JSON.parse(element.dataset.flatpickr)
        : {};
      flatpickr(element, config);
    });

    // Init file input fields
    initFileInputs();
    this.scanModalLinks();

     AdminTheme.init();
  }

  initCkEditor(element) {
    return ClassicEditor.create(element, {
      licenseKey: "GPL",
      plugins: [
        Essentials,
        Paragraph,
        Heading,
        Bold,
        Italic,
        Font,
        List,
        ListProperties,
        Image,
        ImageToolbar,
        ImageUpload,
        ImageInsert,
        Base64UploadAdapter,
        SourceEditing,
        GeneralHtmlSupport,
        FullPage,
      ],
      toolbar: [
        "heading",
        "|",
        "bold",
        "italic",
        "|",
        "fontSize",
        "fontFamily",
        "fontColor",
        "fontBackgroundColor",
        "|",
        "bulletedList",
        "numberedList",
        "|",
        "insertImage",
        "|",
        "sourceEditing",
        "|",
        "undo",
        "redo",
      ],
      image: {
        toolbar: ["imageTextAlternative", "imageStyle:full", "imageStyle:side"],
      },
      codeBlock: {
        languages: [
          { language: "plaintext", label: "Plain text" },
          { language: "c", label: "C" },
          { language: "cs", label: "C#" },
          { language: "cpp", label: "C++" },
          { language: "css", label: "CSS" },
          { language: "diff", label: "Diff" },
          { language: "html", label: "HTML" },
          { language: "java", label: "Java" },
          { language: "javascript", label: "JavaScript" },
          { language: "php", label: "PHP" },
          { language: "python", label: "Python" },
          { language: "ruby", label: "Ruby" },
          { language: "typescript", label: "TypeScript" },
          { language: "xml", label: "XML" },
        ],
      },
      htmlSupport: {
        allow: [
          {
            name: /.*/,
            attributes: true,
            classes: true,
            styles: true,
          },
        ],
        fullPage: {
          allowRenderStylesFromHead: true,
          sanitizeCss(css) {
            return { css, hasChanged: false };
          },
        },
      },
    });
  }

  /**
   *
   * @param {HTMLElement} country
   */
  addressCountryEventAttachment(country) {
    country.addEventListener("change", async (e) => {
      const name = country.name;
      const value = e.target.value;
      const response = await fetch(`/internal/address/${value}/${name}`);
      const results = await response.json();
      if (results.status === true) {
        const html = results.address;

        // Parse HTML string into DOM
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, "text/html");

        // Get address container
        const addressContainer = doc.querySelector("[target-api='address']");

        if (addressContainer) {
          const thisCountryContainer = country.closest(
            "[target-api='address']",
          );
          if (thisCountryContainer) {
            thisCountryContainer.innerHTML = null;
            thisCountryContainer.appendChild(addressContainer);
            setTimeout(() => {
              this.addressCountryEventAttachment(
                addressContainer.querySelector("select"),
              );
            }, 2000);
          }
        }
      }
    });
  }

  async createCsrfToken() {
    const response = await fetch("/internal/csrf-token/generator");
    const results = await response.json();
    if (results.token) {
      return results.token;
    }
    return null;
  }

  /**
   *
   * @param url
   * @param {object} options NOTE body dont stringify it.
   * @returns {Promise<Response>}
   */
  async send(url, options) {
    if (options.method.toLocaleLowerCase() === "post") {
      const body = options.body;
      body["_csrf_token"] = await createCsrfToken();
      options.body = JSON.stringify(body);
    }
    return await fetch(url, options);
  }

  scanModalLinks() {
    const modalEnabledElements = this.context.querySelectorAll(".use-ajax");
    if (modalEnabledElements) {
      const filtedElements = Array.from(modalEnabledElements).filter(
        (element) => element.getAttribute("data-dialog-type") === "modal",
      );

      filtedElements.forEach((element) => {
        element.addEventListener("click", (e) => {
          e.preventDefault();
          this.populateModal(element);
        });
      });
    }
  }

  /**
   *
   * @param {HTMLElement} element
   */
  async populateModal(element) {
    const { dialogOptions, dialogType, linkSystemPath } = element.dataset;

    let options = {};

    // Parse JSON safely
    if (dialogOptions) {
      try {
        options = JSON.parse(dialogOptions);
      } catch (e) {
        console.error("Invalid JSON in dialogOptions:", dialogOptions);
      }
    }

    if (dialogType === "modal") {
      await this.openModal(linkSystemPath, options);
    }
  }

  async openModal(path, options = {}) {
    const width = options.width || 600;

    // Overlay
    const overlay = document.createElement("div");
    overlay.className = "dialog-overlay";

    // Modal container
    const modal = document.createElement("div");
    modal.className = "dialog-modal";
    modal.style.maxWidth = width + "px";

    modal.innerHTML = `
    <div class="dialog-header">
      <span class="dialog-title">Loading...</span>
      <button class="dialog-close">×</button>
    </div>
    <div class="dialog-content">Loading...</div>
  `;

    document.body.appendChild(overlay);
    document.body.appendChild(modal);

    // Close handlers
    const close = () => {
      modal.remove();
      overlay.remove();
    };

    overlay.addEventListener("click", close);
    modal.querySelector(".dialog-close").addEventListener("click", close);

    try {
      const response = await fetch(path, {
        headers: { "X-Requested-With": "XMLHttpRequest" },
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const text = await response.text();

      // Parse HTML
      const parser = new DOMParser();
      const doc = parser.parseFromString(text, "text/html");

      // Extract title
      const title = doc.querySelector("title")?.innerText || "Dialog";

      // Extract main content (fallback to body)
      const main = doc.querySelector("main") || doc.body;

      // Inject into modal
      modal.querySelector(".dialog-title").innerText = title;
      modal.querySelector(".dialog-content").innerHTML = main.innerHTML;
    } catch (err) {
      modal.querySelector(".dialog-title").innerText = "Error";
      modal.querySelector(".dialog-content").innerHTML = `
      <p style="color:red;">Failed to load content</p>
      <pre>${err.message}</pre>
    `;
    }
  }
}

document.addEventListener("DOMContentLoaded", () => {
  window.behaviour = new Behaviour(document);
});
