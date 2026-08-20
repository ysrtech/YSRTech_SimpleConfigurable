/*
    Simple Configurable rewrite of scp_product_extension.js.

    Some of these override/extend Product.Config and Product.OptionsPrice, both of
    which are defined in this repo's js/varien/configurable.js and
    js/varien/product_options.js respectively. Those two files must therefore be
    loaded prior to this file (guaranteed by loading this as a "skin_js" layout item,
    which is always rendered after "js" type items - see getCssJsHtml() in
    Mage_Page_Block_Html_Head).
*/

Product.Config.prototype.getMatchingSimpleProduct = function(){
    var inScopeProductIds = this.getInScopeProductIds();
    if ((typeof inScopeProductIds != 'undefined') && (inScopeProductIds.length == 1)) {
        return inScopeProductIds[0];
    }
    return false;
};

/*
    Find products which are within consideration based on user's selection of
    config options so far
    Returns a normal array containing product ids
    allowedProducts is a normal numeric array containing product ids.
    childProducts is a hash keyed on product id
    optionalAllowedProducts lets you pass a set of products to restrict by,
    in addition to just using the ones already selected by the user
*/
Product.Config.prototype.getInScopeProductIds = function(optionalAllowedProducts) {

    var childProducts = this.config.childProducts;
    var allowedProducts = [];

    if ((typeof optionalAllowedProducts != 'undefined') && (optionalAllowedProducts.length > 0)) {
        allowedProducts = optionalAllowedProducts;
    }

    for(var s=0, len=this.settings.length-1; s<=len; s++) {
        if (this.settings[s].selectedIndex <= 0){
            break;
        }
        var selected = this.settings[s].options[this.settings[s].selectedIndex];
        if (s==0 && allowedProducts.length == 0){
            allowedProducts = selected.config.allowedProducts;
        } else {
            allowedProducts = allowedProducts.intersect(selected.config.allowedProducts).uniq();
        }
    }

    //If we can't find any products (because nothing's been selected most likely)
    //then just use all product ids.
    var productIds;
    if ((typeof allowedProducts == 'undefined') || (allowedProducts.length == 0)) {
        productIds = Object.keys(childProducts);
    } else {
        productIds = allowedProducts;
    }
    return productIds;
};


Product.Config.prototype.getProductIdOfCheapestProductInScope = function(priceType, optionalAllowedProducts) {

    var childProducts = this.config.childProducts;
    var productIds = this.getInScopeProductIds(optionalAllowedProducts);

    var minPrice = Infinity;
    var lowestPricedProdId = false;

    //Get lowest price from product ids.
    for (var x=0, len=productIds.length; x<len; ++x) {
        var thisPrice = Number(childProducts[productIds[x]][priceType]);
        if (thisPrice < minPrice) {
            minPrice = thisPrice;
            lowestPricedProdId = productIds[x];
        }
    }
    return lowestPricedProdId;
};


Product.Config.prototype.getProductIdOfMostExpensiveProductInScope = function(priceType, optionalAllowedProducts) {

    var childProducts = this.config.childProducts;
    var productIds = this.getInScopeProductIds(optionalAllowedProducts);

    var maxPrice = 0;
    var highestPricedProdId = false;

    //Get highest price from product ids.
    for (var x=0, len=productIds.length; x<len; ++x) {
        var thisPrice = Number(childProducts[productIds[x]][priceType]);
        if (thisPrice >= maxPrice) {
            maxPrice = thisPrice;
            highestPricedProdId = productIds[x];
        }
    }
    return highestPricedProdId;
};


Product.Config.prototype.updateFormProductId = function(productId){
    if (!productId) {
        return false;
    }
    var currentAction = $('product_addtocart_form').action;
    var newcurrentAction = currentAction.sub(/product\/\d+\//, 'product/' + productId + '/');
    $('product_addtocart_form').action = newcurrentAction;
    $('product_addtocart_form').product.value = productId;
};


Product.Config.prototype.addParentProductIdToCartForm = function(parentProductId) {
    if (typeof $('product_addtocart_form').cpid != 'undefined') {
        return; //don't create it if we have one..
    }
    var el = document.createElement("input");
    el.type = "hidden";
    el.name = "cpid";
    el.value = parentProductId.toString();
    $('product_addtocart_form').appendChild(el);
};


Product.OptionsPrice.prototype.updateSpecialPriceDisplay = function(price, finalPrice) {

    var prodForm = $('product_addtocart_form');

    var specialPriceBox = prodForm.select('p.special-price');
    var oldPricePriceBox = prodForm.select('p.old-price, p.was-old-price');
    var magentopriceLabel = prodForm.select('span.price-label');

    if (price == finalPrice) {
        specialPriceBox.each(function(x) {x.hide();});
        magentopriceLabel.each(function(x) {x.hide();});
        oldPricePriceBox.each(function(x) {
            x.removeClassName('old-price');
            x.addClassName('was-old-price');
        });
    } else {
        specialPriceBox.each(function(x) {x.show();});
        magentopriceLabel.each(function(x) {x.show();});
        oldPricePriceBox.each(function(x) {
            x.removeClassName('was-old-price');
            x.addClassName('old-price');
        });
    }
};

//This triggers reload of price and other elements that can change
//once all options are selected
Product.Config.prototype.reloadPrice = function() {
    var childProductId = this.getMatchingSimpleProduct();
    var childProducts = this.config.childProducts;
    var usingZoomer = false;
    if (this.config.imageZoomer) {
        usingZoomer = true;
    }

    if (childProductId) {
        var price = childProducts[childProductId]["price"];
        var finalPrice = childProducts[childProductId]["finalPrice"];
        optionsPrice.productPrice = finalPrice;
        optionsPrice.productOldPrice = price;
        optionsPrice.reload();
        optionsPrice.reloadPriceLabels(true);
        optionsPrice.updateSpecialPriceDisplay(price, finalPrice);
        this.updateProductShortDescription(childProductId);
        this.updateProductDescription(childProductId);
        this.updateProductName(childProductId);
        this.updateProductAttributes(childProductId);
        // With addParentToCart the form is left untouched, which is what makes the add behave
        // like stock OpenMage: the configurable's own id and the selected super_attribute values
        // are posted, and the cart line carries the parent SKU with its chosen options. Swapping
        // in the child id and a cpid marker is the module's original behaviour.
        if (!this.config.addParentToCart) {
            this.updateFormProductId(childProductId);
            this.addParentProductIdToCartForm(this.config.productId);
        }
        this.showCustomOptionsBlock(childProductId, this.config.productId);
        if (usingZoomer) {
            this.showFullImageDiv(childProductId, this.config.productId);
        } else {
            this.updateProductImage(childProductId);
        }

    } else {
        var cheapestPid = this.getProductIdOfCheapestProductInScope("finalPrice");
        var price = childProducts[cheapestPid]["price"];
        var finalPrice = childProducts[cheapestPid]["finalPrice"];
        optionsPrice.productPrice = finalPrice;
        optionsPrice.productOldPrice = price;
        optionsPrice.reload();
        optionsPrice.reloadPriceLabels(false);
        optionsPrice.updateSpecialPriceDisplay(price, finalPrice);
        // After reload(), which has just replaced the price with the cheapest child's. Show the
        // span across whatever is still reachable instead: with nothing chosen that is the full
        // range, and each option picked narrows it.
        optionsPrice.showPriceRange(this.getPriceRangeInScope());
        this.updateProductShortDescription(false);
        this.updateProductDescription(false);
        this.updateProductName(false);
        this.updateProductAttributes(false);
        this.showCustomOptionsBlock(false, false);
        if (usingZoomer) {
            this.showFullImageDiv(false, false);
        } else {
            this.updateProductImage(false);
        }
    }
};


/**
 * Selectors the live-update helpers below use to find the page regions they rewrite.
 *
 * The upstream module hard-coded the core base/default markup, which means a theme that
 * names those regions differently gets a silent no-op instead of a live update. Each entry
 * is a list tried in order, so a theme only has to append its own selector - or replace the
 * map wholesale from its own skin JS - rather than fork this file:
 *
 *     Product.Config.prototype.scpTargets.productName = ['#my-theme-title'];
 *
 * Defaults cover core base/default plus the few widely-reused variants (`#mainimage`,
 * `div.product-shop div.description`, `div.add-info`). A region a theme simply does not have
 * resolves to an empty list and the corresponding helper no-ops rather than throwing.
 */
Product.Config.prototype.scpTargets = {
    mainImage:        ['#image', '#mainimage', '#product_addtocart_form p.product-image img'],
    productName:      ['#product_addtocart_form div.product-name h1'],
    shortDescription: ['#product_addtocart_form div.short-description div.std'],
    description:      ['div.box-description div.std', 'div.product-shop div.description'],
    productAttributes: ['div.product-collateral div.box-additional', 'div.product-collateral div.add-info']
};

/**
 * Returns the elements matched by the first selector in scpTargets[name] that matches
 * anything, as a plain array. Empty array when the theme has no such region.
 */
Product.Config.prototype.scpFind = function(name) {
    var selectors = this.scpTargets[name] || [];
    for (var i = 0; i < selectors.length; i++) {
        var found = $$(selectors[i]);
        if (found.length) {
            return found;
        }
    }
    return [];
};

Product.Config.prototype.updateProductImage = function(productId) {
    var imageUrl = this.config.imageUrl;
    if (productId && this.config.childProducts[productId].imageUrl) {
        imageUrl = this.config.childProducts[productId].imageUrl;
    }

    // Undefined means the corresponding "dynamically update" setting is off, so neither the
    // child nor the parent value was put in the config. Leave the page exactly as the server
    // rendered it rather than writing the string "undefined" into it.
    if (typeof imageUrl == 'undefined' || imageUrl === null) {
        return;
    }

    if (!imageUrl) {
        return;
    }

    this.scpFind('mainImage').each(function(el) {
        var dims = el.getDimensions();
        el.src = imageUrl;
        // Keep the rendered box the same size so swapping the src does not reflow the page.
        // Only meaningful when the theme sized the img itself; a CSS-sized image ignores it.
        if (dims.width && dims.height) {
            el.width = dims.width;
            el.height = dims.height;
        }
    });
};

Product.Config.prototype.updateProductName = function(productId) {
    var productName = this.config.productName;
    if (productId && this.config.childProducts[productId].productName) {
        productName = this.config.childProducts[productId].productName;
    }

    // Undefined means the corresponding "dynamically update" setting is off, so neither the
    // child nor the parent value was put in the config. Leave the page exactly as the server
    // rendered it rather than writing the string "undefined" into it.
    if (typeof productName == 'undefined' || productName === null) {
        return;
    }
    this.scpFind('productName').each(function(el) {
        el.innerHTML = productName;
    });
};

Product.Config.prototype.updateProductShortDescription = function(productId) {
    var shortDescription = this.config.shortDescription;
    if (productId && this.config.childProducts[productId].shortDescription) {
        shortDescription = this.config.childProducts[productId].shortDescription;
    }

    // Undefined means the corresponding "dynamically update" setting is off, so neither the
    // child nor the parent value was put in the config. Leave the page exactly as the server
    // rendered it rather than writing the string "undefined" into it.
    if (typeof shortDescription == 'undefined' || shortDescription === null) {
        return;
    }
    this.scpFind('shortDescription').each(function(el) {
        el.innerHTML = shortDescription;
    });
};

Product.Config.prototype.updateProductDescription = function(productId) {
    var description = this.config.description;
    if (productId && this.config.childProducts[productId].description) {
        description = this.config.childProducts[productId].description;
    }

    // Undefined means the corresponding "dynamically update" setting is off, so neither the
    // child nor the parent value was put in the config. Leave the page exactly as the server
    // rendered it rather than writing the string "undefined" into it.
    if (typeof description == 'undefined' || description === null) {
        return;
    }
    this.scpFind('description').each(function(el) {
        el.innerHTML = description;
    });
};

Product.Config.prototype.updateProductAttributes = function(productId) {
    var productAttributes = this.config.productAttributes;
    if (productId && this.config.childProducts[productId].productAttributes) {
        productAttributes = this.config.childProducts[productId].productAttributes;
    }

    // Undefined means the corresponding "dynamically update" setting is off, so neither the
    // child nor the parent value was put in the config. Leave the page exactly as the server
    // rendered it rather than writing the string "undefined" into it.
    if (typeof productAttributes == 'undefined' || productAttributes === null) {
        return;
    }
    //If config product doesn't already have an additional information section,
    //it won't be shown for associated product either. It's too hard to work out
    //where to place it given that different themes use very different html here
    this.scpFind('productAttributes').each(function(el) {
        el.innerHTML = productAttributes;
        decorateTable('product-attribute-specs-table');
    });
};

Product.Config.prototype.showCustomOptionsBlock = function(productId, parentId) {
    var coUrl = this.config.ajaxBaseUrl + "co/?id=" + productId + '&pid=' + parentId;
    var prodForm = $('product_addtocart_form');

    if ($('SCPcustomOptionsDiv') == null) {
        return;
    }

    Effect.Fade('SCPcustomOptionsDiv', { duration: 0.5, from: 1, to: 0.5 });
    if (productId) {
        //Uncomment the line below if you want an ajax loader to appear while any custom
        //options are being loaded.
        //$$('span.scp-please-wait').each(function(el) {el.show()});

        new Ajax.Updater('SCPcustomOptionsDiv', coUrl, {
            method: 'get',
            evalScripts: true,
            onComplete: function() {
                $$('span.scp-please-wait').each(function(el) {el.hide()});
                Effect.Fade('SCPcustomOptionsDiv', { duration: 0.5, from: 0.5, to: 1 });
            }
        });
    } else {
        $('SCPcustomOptionsDiv').innerHTML = '';
        try {window.opConfig = new Product.Options([]);} catch(e) {}
    }
};


Product.Config.prototype.showFullImageDiv = function(productId, parentId) {
    var imgUrl = this.config.ajaxBaseUrl + "image/?id=" + productId + '&pid=' + parentId;
    var prodForm = $('product_addtocart_form');
    var destElement = false;
    var defaultZoomer = this.config.imageZoomer;

    prodForm.select('div.product-img-box').each(function(el) {
        destElement = el;
    });

    //This is needed to reinitialise Product.Zoom correctly,
    //but there's still a race condition (in the onComplete below) which can break it
    try {product_zoom.draggable.destroy();} catch(x) {}

    if (productId) {
        new Ajax.Updater(destElement, imgUrl, {
            method: 'get',
            evalScripts: false,
            onComplete: function() {
                //Product.Zoom needs the *image* (not just the html source from the ajax)
                //to have loaded before it works, hence image object and onload handler
                if ($('image')) {
                    var imgObj = new Image();
                    imgObj.onload = function() {product_zoom = new Product.Zoom('image', 'track', 'handle', 'zoom_in', 'zoom_out', 'track_hint'); };
                    imgObj.src = $('image').src;
                } else {
                    destElement.innerHTML = defaultZoomer;
                    product_zoom = new Product.Zoom('image', 'track', 'handle', 'zoom_in', 'zoom_out', 'track_hint');
                }
            }
        });
    } else {
        destElement.innerHTML = defaultZoomer;
        product_zoom = new Product.Zoom('image', 'track', 'handle', 'zoom_in', 'zoom_out', 'track_hint');
    }
};


/**
 * Lowest and highest price among the associated products still reachable from the current
 * selection, as {regular: [min, max], final: [min, max]}.
 *
 * Picking one attribute of several rarely identifies a single product, but it does narrow the
 * field - so the price should narrow with it rather than sit on the full range until the last
 * option is chosen.
 */
Product.Config.prototype.getPriceRangeInScope = function() {
    var childProducts = this.config.childProducts;
    var ids = this.getInScopeProductIds();
    var regular = [];
    var final = [];

    for (var i = 0; i < ids.length; i++) {
        var child = childProducts[ids[i]];
        if (!child) {
            continue;
        }
        regular.push(Number(child.price));
        final.push(Number(child.finalPrice));
    }

    if (!regular.length) {
        return null;
    }

    return {
        regular: [Math.min.apply(null, regular), Math.max.apply(null, regular)],
        final:   [Math.min.apply(null, final),   Math.max.apply(null, final)]
    };
};

/**
 * Writes a price range into the price box, or a single figure when both ends match.
 */
Product.OptionsPrice.prototype.showPriceRange = function(range) {
    if (!range) {
        return;
    }

    var format = function(pair) {
        var low = this.formatPrice(pair[0]);
        return Math.abs(pair[1] - pair[0]) < 0.00001 ? low : low + ' - ' + this.formatPrice(pair[1]);
    }.bind(this);

    var targets = [
        { id: 'product-price-' + this.productId, text: format(range.final) },
        { id: 'old-price-' + this.productId,     text: format(range.regular) }
    ];

    targets.each(function(target) {
        [target.id, target.id + this.duplicateIdSuffix].each(function(id) {
            var el = $(id);
            if (!el) {
                return;
            }
            // The id sits either on the money element itself or on a wrapper around it,
            // depending on whether a special price is in play.
            var money = el.hasClassName('price') ? el : (el.select('span.price')[0] || el);
            money.innerHTML = target.text;
        });
    }.bind(this));
};

/**
 * Puts the min-max spans back into the price box.
 *
 * The server renders the range, but optionsPrice.reload() overwrites those elements with a
 * single figure. That is what should happen once a shopper has picked a full set of options -
 * the price is then known exactly - but when the selection is incomplete or cleared, the range
 * is the honest answer, so it is written back.
 */
Product.OptionsPrice.prototype.restorePriceRange = function() {
    if (typeof spConfig == 'undefined') {
        return;
    }

    var ranges = [
        { id: 'product-price-' + this.productId, text: spConfig.config.priceRange },
        { id: 'old-price-' + this.productId,     text: spConfig.config.oldPriceRange }
    ];

    ranges.each(function(range) {
        if (!range.text) {
            return;
        }
        [range.id, range.id + this.duplicateIdSuffix].each(function(id) {
            var el = $(id);
            if (!el) {
                return;
            }
            // The id sits either on the money element itself or on a wrapper around it,
            // depending on whether a special price is in play.
            var target = el.hasClassName('price') ? el : (el.select('span.price')[0] || el);
            target.innerHTML = range.text;
        });
    }.bind(this));
};

Product.OptionsPrice.prototype.reloadPriceLabels = function(productPriceIsKnown) {
    var priceFromLabel = '';

    if (!productPriceIsKnown && typeof spConfig != "undefined") {
        priceFromLabel = spConfig.config.priceFromLabel;
    }

    var priceSpanId = 'configurable-price-from-' + this.productId;
    var duplicatePriceSpanId = priceSpanId + this.duplicateIdSuffix;

    if ($(priceSpanId) && $(priceSpanId).select('span.configurable-price-from-label')) {
        $(priceSpanId).select('span.configurable-price-from-label').each(function(label) {
            label.innerHTML = priceFromLabel;
        });
    }

    if ($(duplicatePriceSpanId) && $(duplicatePriceSpanId).select('span.configurable-price-from-label')) {
        $(duplicatePriceSpanId).select('span.configurable-price-from-label').each(function(label) {
            label.innerHTML = priceFromLabel;
        });
    }
};


//Forces the 'next' element to have its optionLabels reloaded too
Product.Config.prototype.configureElement = function(element) {
    this.reloadOptionLabels(element);
    if (element.value) {
        this.state[element.config.id] = element.value;
        if (element.nextSetting) {
            element.nextSetting.disabled = false;
            this.fillSelect(element.nextSetting);
            this.reloadOptionLabels(element.nextSetting);
            this.resetChildren(element.nextSetting);
        }
    } else {
        this.resetChildren(element);
    }
    this.reloadPrice();
};


//Uses absolute price ranges rather than price differentials
Product.Config.prototype.reloadOptionLabels = function(element){
    var childProducts = this.config.childProducts;

    //Don't update elements that have a selected option
    if (element.options[element.selectedIndex].config) {
        return;
    }

    for (var i=0; i<element.options.length; i++) {
        if (element.options[i].config) {
            var cheapestPid = this.getProductIdOfCheapestProductInScope("finalPrice", element.options[i].config.allowedProducts);
            var mostExpensivePid = this.getProductIdOfMostExpensiveProductInScope("finalPrice", element.options[i].config.allowedProducts);
            var cheapestFinalPrice = childProducts[cheapestPid]["finalPrice"];
            var mostExpensiveFinalPrice = childProducts[mostExpensivePid]["finalPrice"];
            element.options[i].text = this.getOptionLabel(element.options[i].config, cheapestFinalPrice, mostExpensiveFinalPrice);
        }
    }
};

//Shows absolute price ranges rather than price differentials
Product.Config.prototype.getOptionLabel = function(option, lowPrice, highPrice){

    var str = option.label;

    if (!this.config.showPriceRangesInOptions) {
        return str;
    }

    var to = ' ' + this.config.rangeToLabel + ' ';
    var separator = ': ';

    var lowPrices = this.getTaxPrices(lowPrice);
    var highPrices = this.getTaxPrices(highPrice);

    if (lowPrice && highPrice) {
        if (lowPrice != highPrice) {
            if (this.taxConfig.showBothPrices) {
                str+= separator + this.formatPrice(lowPrices[2], false) + ' (' + this.formatPrice(lowPrices[1], false) + ' ' + this.taxConfig.inclTaxTitle + ')';
                str+= to + this.formatPrice(highPrices[2], false) + ' (' + this.formatPrice(highPrices[1], false) + ' ' + this.taxConfig.inclTaxTitle + ')';
            } else {
                str+= separator + this.formatPrice(lowPrices[0], false);
                str+= to + this.formatPrice(highPrices[0], false);
            }
        } else {
            if (this.taxConfig.showBothPrices) {
                str+= separator + this.formatPrice(lowPrices[2], false) + ' (' + this.formatPrice(lowPrices[1], false) + ' ' + this.taxConfig.inclTaxTitle + ')';
            } else {
                str+= separator + this.formatPrice(lowPrices[0], false);
            }
        }
    }
    return str;
};


//Refactored price calculations into a separate function
Product.Config.prototype.getTaxPrices = function(price) {
    price = parseFloat(price);
    var tax, excl, incl;

    if (this.taxConfig.includeTax) {
        tax = price / (100 + this.taxConfig.defaultTax) * this.taxConfig.defaultTax;
        excl = price - tax;
        incl = excl*(1+(this.taxConfig.currentTax/100));
    } else {
        tax = price * (this.taxConfig.currentTax / 100);
        excl = price;
        incl = excl + tax;
    }

    if (this.taxConfig.showIncludeTax || this.taxConfig.showBothPrices) {
        price = incl;
    } else {
        price = excl;
    }

    return [price, incl, excl];
};


//Forces price labels to be updated on load
//so that the first select shows ranges from the start
document.observe("dom:loaded", function() {
    //Really only needs to be the first element that has configureElement set on it,
    //rather than all.
    if (!$('product_addtocart_form')) {
        return;
    }
    $('product_addtocart_form').getElements().each(function(el) {
        if (el.type == 'select-one') {
            if (el.options && (el.options.length > 1)) {
                el.options[0].selected = true;
                spConfig.reloadOptionLabels(el);
            }
        }
    });
});
