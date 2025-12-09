(function () {
  "use strict";

  const dom = {
    productImage: document.getElementById("pw-product-image"),
    imageTrigger: document.getElementById("pw-image-trigger"),
    title: document.getElementById("pw-title"),
    price: document.getElementById("pw-price"),
    description: document.getElementById("pw-description"),
    variantGroup: document.getElementById("pw-variant-group"),
    variantSelect: document.getElementById("pw-variant"),
    variantError: document.getElementById("pw-variant-error"),
    quantityInput: document.getElementById("pw-quantity"),
    qtyDecBtn: document.getElementById("pw-qty-dec"),
    qtyIncBtn: document.getElementById("pw-qty-inc"),
    qtyError: document.getElementById("pw-qty-error"),
    addBtn: document.getElementById("pw-add-btn"),
    btnText: document.getElementById("pw-btn-text"),
    addStatus: document.getElementById("pw-add-status"),
    lightbox: document.getElementById("pw-lightbox"),
    lightboxImg: document.getElementById("pw-lightbox-img"),
    lightboxClose: document.getElementById("pw-lightbox-close"),
  };

  let productData = null;
  let isAdding = false;

  /**
   * Format numeric price with currency using Intl; fallback to simple formatting.
   * @param {number} price
   * @param {string} currency
   * @returns {string}
   */
  function formatPrice(price, currency) {
    try {
      return new Intl.NumberFormat(undefined, {
        style: "currency",
        currency,
      }).format(price);
    } catch (err) {
      return `${currency} ${price.toFixed(2)}`;
    }
  }

  function getMaxQuantity() {
    return productData && Number.isFinite(productData.maxQuantity)
      ? productData.maxQuantity
      : 10;
  }

  function updateDisplayedPrice() {
    if (!productData) return;
    let computed = productData.price;

    if (productData.variants && productData.variants.length > 0) {
      const sel = dom.variantSelect.value;
      const variant = productData.variants.find((v) => v.id === sel);
      if (variant && typeof variant.priceDiff === "number") {
        computed += variant.priceDiff;
      }
    }

    dom.price.textContent = formatPrice(computed, productData.currency);
  }

  function updateQuantityButtons() {
    const qty = parseInt(dom.quantityInput.value, 10) || 1;
    const max = getMaxQuantity();
    dom.qtyDecBtn.disabled = qty <= 1;
    dom.qtyIncBtn.disabled = qty >= max;
  }

  function showError(element, message) {
    element.textContent = message || "";
    element.style.display = message ? "block" : "none";
  }

  function validateInputs() {
    let valid = true;

    const qty = parseInt(dom.quantityInput.value, 10);
    const max = getMaxQuantity();
    if (Number.isNaN(qty) || qty < 1 || qty > max) {
      showError(dom.qtyError, `Please enter a quantity between 1 and ${max}`);
      valid = false;
    } else {
      showError(dom.qtyError, "");
    }

    if (productData.variants && productData.variants.length > 0) {
      if (!dom.variantSelect.value) {
        showError(dom.variantError, "Please select an option");
        valid = false;
      } else {
        showError(dom.variantError, "");
      }
    }

    return valid;
  }

  function openLightbox() {
    dom.lightbox.classList.add("pw--active");
    dom.lightboxClose.focus();
    document.body.style.overflow = "hidden";
  }

  function closeLightbox() {
    dom.lightbox.classList.remove("pw--active");
    dom.imageTrigger.focus();
    document.body.style.overflow = "";
  }

  async function handleAddToCart() {
    if (isAdding || !productData) return;

    if (!validateInputs()) return;

    isAdding = true;
    dom.addBtn.disabled = true;
    dom.btnText.innerHTML =
      '<span class="pw__spinner" aria-hidden="true"></span> Adding...';
    dom.addStatus.textContent = "Adding item to cart";

    let finalPrice = productData.price;
    let selectedVariant = null;

    if (productData.variants && productData.variants.length > 0) {
      selectedVariant =
        productData.variants.find((v) => v.id === dom.variantSelect.value) ||
        null;
      if (selectedVariant && typeof selectedVariant.priceDiff === "number") {
        finalPrice += selectedVariant.priceDiff;
      }
    }

    const quantity = parseInt(dom.quantityInput.value, 10);

    const payload = {
      id: productData.id,
      title: productData.title,
      price: finalPrice,
      quantity,
      variant: selectedVariant,
      currency: productData.currency,
    };

    await new Promise((resolve) => setTimeout(resolve, 800));

    const evt = new CustomEvent("product:add", {
      detail: payload,
      bubbles: true,
    });
    window.dispatchEvent(evt);

    if (typeof window.onProductAdd === "function") {
      try {
        window.onProductAdd(payload);
      } catch (e) {
        console.error(e);
      }
    }

    dom.btnText.textContent = "Added!";
    dom.addStatus.textContent = "Item added to cart successfully";

    setTimeout(() => {
      dom.btnText.textContent = "Add to Cart";
      dom.addBtn.disabled = false;
      isAdding = false;
    }, 1500);
  }

  /* ---------------------------
     Initialization
     --------------------------- */

  /**
   * Populate UI with a product object and normalize the product.
   * Required fields: id, title, price (number).
   *
   * Allowed product shape:
   * {
   *   id: string,
   *   title: string,
   *   price: number,
   *   currency?: string,
   *   image?: string,
   *   description?: string (can contain HTML),
   *   variants?: [{ id, label, priceDiff }],
   *   maxQuantity?: number
   * }
   */
  function initProductWidget(product) {
    if (
      !product ||
      !product.id ||
      !product.title ||
      typeof product.price !== "number"
    ) {
      console.error(
        "initProductWidget: invalid product. Required: id, title, price(number)."
      );
      return;
    }

    productData = {
      id: product.id,
      title: product.title,
      price: product.price,
      currency: product.currency || "USD",
      image: product.image || "",
      description: product.description || "",
      variants: Array.isArray(product.variants) ? product.variants : [],
      maxQuantity: Number.isFinite(product.maxQuantity)
        ? product.maxQuantity
        : 10,
    };

    // Fill UI
    dom.productImage.src = productData.image;
    dom.productImage.alt = productData.title;
    dom.lightboxImg.src = productData.image;
    dom.lightboxImg.alt = productData.title;
    dom.title.textContent = productData.title;

    if (productData.description && productData.description.includes("<")) {
      dom.description.innerHTML = productData.description;
    } else {
      dom.description.textContent = productData.description;
    }

    // Quantity defaults and attributes
    dom.quantityInput.value = 1;
    dom.quantityInput.max = productData.maxQuantity;
    updateQuantityButtons();

    // Variants
    if (productData.variants.length > 0) {
      dom.variantGroup.classList.remove("pw--hidden");
      dom.variantSelect.innerHTML = "";
      const placeholder = document.createElement("option");
      placeholder.value = "";
      placeholder.textContent = "اختر اللون المناسب";
      dom.variantSelect.appendChild(placeholder);

      productData.variants.forEach((v) => {
        const option = document.createElement("option");
        option.value = v.id;
        let label = v.label || v.id;
        if (typeof v.priceDiff === "number" && v.priceDiff !== 0) {
          const sign = v.priceDiff > 0 ? "+" : "";
          label += ` (${sign}${formatPrice(
            v.priceDiff,
            productData.currency
          )})`;
        }
        option.textContent = label;
        dom.variantSelect.appendChild(option);
      });
    } else {
      dom.variantGroup.classList.add("pw--hidden");
      dom.variantSelect.innerHTML = "";
    }

    updateDisplayedPrice();
  }

  /* ---------------------------
     Event bindings
     --------------------------- */

  // Lightbox open (click / keyboard)
  dom.imageTrigger.addEventListener("click", openLightbox);
  dom.imageTrigger.addEventListener("keydown", (e) => {
    if (e.key === "Enter" || e.key === " ") {
      e.preventDefault();
      openLightbox();
    }
  });

  // Lightbox close actions
  dom.lightboxClose.addEventListener("click", closeLightbox);
  dom.lightbox.addEventListener("click", (e) => {
    if (e.target === dom.lightbox) closeLightbox();
  });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && dom.lightbox.classList.contains("pw--active"))
      closeLightbox();
  });

  // Variant change updates price
  dom.variantSelect.addEventListener("change", updateDisplayedPrice);

  // Quantity controls
  dom.qtyDecBtn.addEventListener("click", () => {
    const current = parseInt(dom.quantityInput.value, 10) || 1;
    if (current > 1) {
      dom.quantityInput.value = current - 1;
      updateQuantityButtons();
    }
  });

  dom.qtyIncBtn.addEventListener("click", () => {
    const current = parseInt(dom.quantityInput.value, 10) || 1;
    const max = getMaxQuantity();
    if (current < max) {
      dom.quantityInput.value = current + 1;
      updateQuantityButtons();
    }
  });

  dom.quantityInput.addEventListener("change", () => {
    let val = parseInt(dom.quantityInput.value, 10);
    const max = getMaxQuantity();
    if (Number.isNaN(val) || val < 1) val = 1;
    if (val > max) val = max;
    dom.quantityInput.value = val;
    updateQuantityButtons();
    showError(dom.qtyError, "");
  });

  /* ---------------------------
     Reviews and Related Products Logic
     --------------------------- */

  // DOM elements for new sections
  const reviewDom = {
    form: document.getElementById("review-form"),
    nameInput: document.getElementById("reviewer-name"),
    ratingInputDiv: document.getElementById("rating-input"),
    ratingValueInput: document.getElementById("review-rating-value"),
    textInput: document.getElementById("review-text"),
    reviewsList: document.getElementById("reviews-list"),
    relatedProductsList: document.getElementById("related-products-list"),
  };

  // Static/Demo Data
  let productReviews = [
    {
      name: "أحمد محمود",
      rating: 5,
      text: "سماعات ممتازة، جودة صوت نقية جداً وعزل الضوضاء فعال. تستحق كل قرش!",
      date: "2024-05-10",
    },
    {
      name: "فاطمة علي",
      rating: 4,
      text: "جيدة جداً لكن حجمها كبير قليلاً. البطارية تدوم طويلاً وهو أمر رائع.",
      date: "2024-05-01",
    },
    {
      name: "يوسف خالد",
      rating: 5,
      text: "أفضل سماعات امتلكتها على الإطلاق. الراحة وجودة البناء ممتازة.",
      date: "2024-04-25",
    },
  ];

  const relatedProductsData = [
    {
      id: "rel-001",
      name: "حامل سماعات فاخر",
      price: "15,000 SYP",
      image:
        "https://images.unsplash.com/photo-1628178877524-7472df0d5885?w=300&h=300&fit=crop",
      rating: 4.5,
    },
    {
      id: "rel-002",
      name: "حافظة سفر صلبة",
      price: "10,500 SYP",
      image:
        "https://images.unsplash.com/photo-1542313620-e22115167f5b?w=300&h=300&fit=crop",
      rating: 5,
    },
    {
      id: "rel-003",
      name: "كابل شحن USB-C طويل",
      price: "3,000 SYP",
      image:
        "https://images.unsplash.com/photo-1582268611958-ab50157de55d?w=300&h=300&fit=crop",
      rating: 4,
    },
    {
      id: "rel-004",
      name: "اشتراك موسيقى لمدة سنة",
      price: "60,000 SYP",
      image:
        "https://images.unsplash.com/photo-1458560871784-56d23406b291?w=300&h=300&fit=crop",
      rating: 4.8,
    },
  ];

  /**
   * Helper function to generate star rating display string.
   * @param {number} rating - The rating value (e.g., 4.5)
   * @returns {string} HTML string of gold stars.
   */
  function getStarDisplay(rating) {
    const fullStar = "★";
    const emptyStar = "☆";
    let stars = "";
    const fullStars = Math.floor(rating);

    // Add full stars
    stars += fullStar.repeat(fullStars);

    // Add empty stars to complete 5 total stars
    stars += emptyStar.repeat(5 - fullStars);

    return stars;
  }

  /**
   * Renders a single review card.
   * @param {object} review - The review object.
   * @returns {string} HTML string for the review card.
   */
  function createReviewCard(review) {
    const dateOptions = { year: "numeric", month: "long", day: "numeric" };
    const formattedDate = new Date(review.date).toLocaleDateString(
      "ar-EG",
      dateOptions
    );

    return `
      <div class="review-card">
        <div class="review-header">
          <span class="review-name">${review.name}</span>
          <span class="review-rating-display" aria-label="Rating: ${
            review.rating
          } out of 5">${getStarDisplay(review.rating)}</span>
        </div>
        <p class="review-text">${review.text}</p>
        <span class="review-date">${formattedDate}</span>
      </div>
    `;
  }

  /**
   * Populates the reviews list with the current productReviews array.
   */
  function renderReviews() {
    if (!reviewDom.reviewsList) return;
    reviewDom.reviewsList.innerHTML = productReviews
      .map(createReviewCard)
      .join("");
  }

  /**
   * Handles the submission of a new review from the form.
   * @param {Event} e - The form submit event.
   */
  function handleReviewSubmit(e) {
    e.preventDefault();

    const name = reviewDom.nameInput.value.trim();
    const rating = parseInt(reviewDom.ratingValueInput.value, 10);
    const text = reviewDom.textInput.value.trim();

    if (!name || rating < 1 || rating > 5 || !text) {
      alert("الرجاء ملء جميع الحقول واختيار تقييم.");
      return;
    }

    const newReview = {
      name: name,
      rating: rating,
      text: text,
      date: new Date().toISOString().split("T")[0], // YYYY-MM-DD
    };

    // Add new review to the beginning of the array and re-render
    productReviews.unshift(newReview);
    renderReviews();

    // Clear the form and reset stars
    reviewDom.form.reset();
    resetRatingStars();

    alert("شكراً لك! تم إرسال تقييمك بنجاح.");
  }

  /**
   * Renders the interactive star rating input.
   */
  function renderRatingInput() {
    reviewDom.ratingInputDiv.innerHTML = "";
    for (let i = 5; i >= 1; i--) {
      const star = document.createElement("span");
      star.className = "rating-star";
      star.textContent = "★";
      star.setAttribute("data-rating", i);
      star.setAttribute("role", "radio");
      star.setAttribute("aria-checked", "false");
      star.setAttribute("tabindex", "0");

      star.addEventListener("click", () => {
        reviewDom.ratingValueInput.value = i;
        updateRatingStars(i);
      });

      star.addEventListener("keydown", (e) => {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          reviewDom.ratingValueInput.value = i;
          updateRatingStars(i);
        }
      });

      star.addEventListener("mouseover", () => updateRatingStars(i, true));
      star.addEventListener("mouseout", () =>
        updateRatingStars(parseInt(reviewDom.ratingValueInput.value, 10) || 0)
      );

      reviewDom.ratingInputDiv.appendChild(star);
    }
  }

  /**
   * Updates the visual state of the star rating.
   * @param {number} selectedRating - The rating to highlight.
   * @param {boolean} isHover - If the update is due to a hover action.
   */
  function updateRatingStars(selectedRating, isHover = false) {
    const stars = reviewDom.ratingInputDiv.querySelectorAll(".rating-star");
    stars.forEach((star) => {
      const rating = parseInt(star.getAttribute("data-rating"), 10);

      // Remove 'selected' class for a clean slate
      star.classList.remove("selected");
      star.setAttribute("aria-checked", "false");

      // Apply 'selected' class if the star's rating is less than or equal to the selected/hovered rating
      if (rating <= selectedRating) {
        star.classList.add("selected");
        if (!isHover) {
          star.setAttribute("aria-checked", "true");
        }
      }
    });
  }

  /**
   * Resets the rating stars visual and value.
   */
  function resetRatingStars() {
    reviewDom.ratingValueInput.value = "";
    updateRatingStars(0);
  }

  /* ---------------------------
     Related Products Logic
     --------------------------- */

  /**
   * Renders a single related product card.
   * @param {object} product - The product object.
   * @returns {string} HTML string for the product card.
   */
  function createProductCard(product) {
    return `
      <a href="#" class="product-card" aria-label="${product.name}">
        <img src="${product.image}" alt="${
      product.name
    }" class="product-card-image" loading="lazy" />
        <p class="product-card-name">${product.name}</p>
        <div class="product-card-rating">${getStarDisplay(product.rating)}</div>
        <p class="product-card-price">${product.price}</p>
        <button class="product-card-btn" type="button">مشاهدة</button>
      </a>
    `;
  }

  /**
   * Populates the related products list.
   */

  function renderRelatedProducts() {
    if (!reviewDom.relatedProductsList) return;
    reviewDom.relatedProductsList.innerHTML = relatedProductsData
      .map(createProductCard)
      .join("");
  }
  // Bind review form submission
  if (reviewDom.form) {
    reviewDom.form.addEventListener("submit", handleReviewSubmit);
  }

  // Initial render of the new sections on load
  document.addEventListener("DOMContentLoaded", () => {
    // Ensure existing initialization runs first
    window.initProductWidget(exampleProduct);

    // Initialize new features
    renderRatingInput();
    renderReviews();
    renderRelatedProducts();
  });
  // Add to cart
  dom.addBtn.addEventListener("click", handleAddToCart);

  /* ---------------------------
     Public API
     --------------------------- */
  window.initProductWidget = initProductWidget;

  /* ---------------------------
     Demo example initialization (same example as original)
     --------------------------- */
  const exampleProduct = {
    id: "prod-001",
    title: "Premium Wireless Headphones",
    price: 50000,
    currency: "SYP",
    image:
      "https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=600&h=600&fit=crop",
    description:
      "استمتع بصوت فائق الوضوح مع سماعات الرأس اللاسلكية الفاخرة لدينا. تتميز بخاصية إلغاء الضوضاء النشطة، وعمر بطارية يدوم 30 ساعة، ووسائد أذن مريحة للغاية من إسفنج الذاكرة.",
    variants: [
      { id: "black", label: "اسود", priceDiff: 0 },
      { id: "silver", label: "فضي", priceDiff: 0 },
      { id: "rose", label: "ذهبي", priceDiff: 0 },
    ],
    maxQuantity: 5,
  };

  // Initialize demo widget on load
  initProductWidget(exampleProduct);
})();
