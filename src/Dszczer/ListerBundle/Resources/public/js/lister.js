if (typeof jQuery === "undefined") {
  // plugin won"t work without jQuery
  throw new Error("Lister dynamic require jQuery");
}

(function ($) {
  "use strict";

  /**
   * DOM Data attribute storage name.
   * @internal
   */
  var LISTER_DATA_PLUGIN = "lister";
  /**
   * Default configuration.
   * @internal
   */
  var LISTER_DEFAULTS = {
    pageLinks: 7,
    filterContainerId: null,
    listContainerId: null,
    paginationContainerId: null,
    paginationLinkSelector: "a",
    customFilterAction: null,
    customPaginationAction: null,
    customSortingAction: null,
    customReloadList: null
  };

  /*
   * Lister class definition
   */

  /**
   * Lister constructor.
   * @param el
   * @param options
   * @constructor
   */
  var Lister = function (el, options) {
    this.element = el;

    // configure list
    this.configure.call(this, options);
    $(this.element).trigger(Lister.EVENTS.init, [this, options]);
  };

  /**
   * List of events.
   */
  Lister.EVENTS = {
    error: "lister:error",
    configLoad: "lister:configLoad",
    init: "lister:init",
    listLoad: "lister:load",
    filterLoad: "lister:filterLoad",
    filterApply: "lister:filterApply",
    sortLoad: "lister:sortLoad",
    sortApply: "lister:sortApply",
    paginationLoad: "lister:paginationLoad",
    paginationApply: "lister:paginationApply"
  };

  /**
   * Merge default options with provided one.
   * @param options
   */
  Lister.prototype.configure = function (options) {
    $(this.element).trigger(Lister.EVENTS.configLoad, [this, options]);
    if (this.options) {
      this.options = $.extend({}, LISTER_DEFAULTS, this.options, options);
    } else {
      this.options = $.extend({}, LISTER_DEFAULTS, options);
    }
    this.revalidate.call(this);
  };

  /**
   * Check options integrity (validate them).
   */
  Lister.prototype.revalidate = function () {
    this.isFilters = true;
    this.enabled = true;
    this.isPagination = true;

    // validate Lister properties
    if (this.options.filterContainerId === null) {
      this.isFilters = false;
    }
    else if (document.getElementById(this.options.filterContainerId) === null) {
      this.isFilters = false;
      $(this.element).trigger(Lister.EVENTS.error, [this, "Lister: filterContainerId is not a valid id!"]);
    }
    else if ($("#" + this.options.filterContainerId).find("form").length === 0) {
      this.isFilters = false;
      $(this.element).trigger(Lister.EVENTS.error, [this, "Lister: filterContainerId do not contain any form!"]);
    }

    if (this.options.listContainerId === null) {
      this.enabled = false;
      $(this.element).trigger(Lister.EVENTS.error, [this, "Lister: listContainerId option not set!"]);
    }
    else if (document.getElementById(this.options.listContainerId) === null) {
      this.enabled = false;
      $(this.element).trigger(Lister.EVENTS.error, [this, "Lister: listContainerId is not a valid id!"]);
    }

    if (this.options.paginationContainerId === null) {
      this.isPagination = false;
    }
    else if (document.getElementById(this.options.paginationContainerId) === null) {
      this.isPagination = false;
      $(this.element).trigger(Lister.EVENTS.error, [this, "Lister: paginationContainerId is not a valid id!"]);
    }

    if (this.isFilters) {
      this.dynamicFilterForm();
    }
    this.dynamicSortingForm();
    if (this.isPagination) {
      this.dynamicPagination();
    }
  };

  /**
   * Handle prepared request.
   * @param method
   * @param url
   * @param data
   * @param success
   */
  Lister.prototype.handleRequest = function (method, url, data, success) {
    var context = this;
    context.muteList.call(context, true);
    $.ajax({
      type: method,
      url: url,
      data: data,
      success: function (data) {
        context.muteList.call(context, false);
        var status = data.status.type;
        var handled = context.handleResponse.call(context, data, status);
        success.call(context, handled);
      },
      error: function (data, status) {
        context.muteList.call(context, false);
        context.handleException.call(context, data, status);
      }
    });
  };

  /**
   * Handle request exception.
   * @param jqXHR
   * @param textStatus
   * @param errorThrown
   */
  Lister.prototype.handleException = function (jqXHR, textStatus, errorThrown) {
    var jsonResponse = $.parseJSON(jqXHR.responseText);
    window.alert("Lister: " + (errorThrown ? errorThrown : textStatus) + ", " + jsonResponse.status.message);
  };

  /**
   * Handle raw response and decorate it.
   * @param data
   * @param status
   * @return {*}
   */
  Lister.prototype.handleResponse = function (data, status) {
    return {
      data: data,
      status: status
    };
  };

  /**
   * Handle filter form.
   */
  Lister.prototype.dynamicFilterForm = function () {
    if (!this.isFilters || !this.enabled) {
      return;
    }

    var context = this;
    var $form = $("#" + context.options.filterContainerId).find("form").first();
    var $apply = $form.find("[name$=submit\\]]").first();
    var $reset = $form.find("[name$=reset\\]]").first();

    function captureClick(e, clickContext) {
      clickContext = clickContext || this;
      $form.find("[type=submit]").removeAttr("clicked");
      $(clickContext).attr("clicked", "true");
    }

    function resetClick() {
      $form.find("input, textarea, select").not("[type=submit]").each(function () {
        $(this).val(null);
      });
      captureClick(false, this);
    }

    $apply.off("click", captureClick).on("click", captureClick);
    $reset.off("click", resetClick).on("click", resetClick);
    $form.off("submit").on("submit", function () {
      var data = $form.serializeArray();
      var $clicked = $("[type=submit][clicked=true]").first();
      data.push({name: $clicked.attr("name"), value: ""});

      if (typeof context.customFilterAction === "function") {
        var custom = context.customFilterAction.call(context, $form, $clicked);
        if (
          typeof custom === "object" &&
          typeof custom.method !== "undefined" &&
          typeof custom.action !== "undefined" &&
          typeof custom.data === "object"
        ) {
          context.handleRequest.call(context, custom.method, custom.action, custom.data, context.reloadList);
        } else {
          $(context.element).trigger(Lister.EVENTS.error, [context, "Lister: invalid customFilterAction return value"]);
        }
      } else {
        context.handleRequest.call(context, $form.attr("method"), $form.attr("action"), data, context.reloadList);
      }
      $(context.element).trigger(Lister.EVENTS.filterApply, [context, $form, $clicked]);

      return false;
    });
  };

  /**
   * Handle sorting forms.
   */
  Lister.prototype.dynamicSortingForm = function () {
    if (!this.enabled) {
      return;
    }

    var context = this;
    var $sortingForms = $("#" + context.options.listContainerId).find("form[name$=_sorter]");

    $sortingForms.each(function () {
      var $form = $(this);

      $form.off("submit").on("submit", function () {
        var data = $form.serializeArray();
        data.push({name: $form.find("[type=submit]").first().attr("name"), value: ""});
        if (typeof context.customSortingAction === "function") {
          var custom = context.customSortingAction.call(context, $form);
          if (
            typeof custom === "object" &&
            typeof custom.method !== "undefined" &&
            typeof custom.action !== "undefined" &&
            typeof custom.data === "object"
          ) {
            context.handleRequest.call(context, custom.method, custom.action, custom.data, context.reloadList);
          } else {
            $(context.element).trigger(Lister.EVENTS.error, [context, "Lister: invalid customSortingAction return value"]);
          }
        } else {
          context.handleRequest.call(context, $form.attr("method"), $form.attr("action"), data, context.reloadList);
        }
        $(context.element).trigger(Lister.EVENTS.sortApply, [context]);

        return false;
      });
    });
  };

  /**
   * Handle pagination.
   */
  Lister.prototype.dynamicPagination = function () {
    if (!this.isPagination || !this.enabled) {
      return;
    }

    var context = this;
    var $paginationLinks = $("#" + context.options.paginationContainerId).find(context.options.paginationLinkSelector);

    $paginationLinks.each(function () {
      var $link = $(this);

      $link.off('click').on('click', function (e) {
        var href = decodeURIComponent($link.attr('href'));

        if (href !== "#") {
          if (typeof context.customPaginationAction === "function") {
            var custom = context.customPaginationAction.call(context, $link);
            if (
              typeof custom === "object" &&
              typeof custom.method !== "undefined" &&
              typeof custom.action !== "undefined" &&
              typeof custom.data === "object"
            ) {
              context.handleRequest.call(context, custom.method, custom.action, custom.data, context.reloadList);
            } else {
              $(context.element).trigger(Lister.EVENTS.error, [context, "Lister: invalid customPaginationAction return value"]);
            }
          } else {
            context.handleRequest.call(context, "post", href, null, context.reloadList);
          }
          $(context.element).trigger(Lister.EVENTS.paginationApply, [context]);
        }

        e.preventDefault();
      });
    });

    $(context.element).trigger(Lister.EVENTS.paginationApply, [context]);
  };

  /**
   * Reload list DOM: filters, list and pagination.
   * @param decor
   */
  Lister.prototype.reloadList = function (decor) {
    var context = this;

    if (typeof context.options.customReloadList === "function") {
      context.options.customReloadList.call(context, decor);
      context.revalidate.call(context);
    } else {
      $("#" + context.options.listContainerId).replaceWith(decor.data.listHTML);
      $(context.element).trigger(Lister.EVENTS.sortLoad, [context, decor]);

      if (context.isFilters || $("#" + context.options.filterContainerId).length > 0) {
        $("#" + context.options.filterContainerId).replaceWith(decor.data.filterHTML);
        $(context.element).trigger(Lister.EVENTS.filterLoad, [context, decor]);
      }

      if (context.isPagination || $("#" + context.options.paginationContainerId).length > 0) {
        $("#" + context.options.paginationContainerId).replaceWith(decor.data.paginationHTML);
        $(context.element).trigger(Lister.EVENTS.paginationLoad, [context, decor]);
      }
      context.revalidate.call(context);
    }
    $(context.element).trigger(Lister.EVENTS.listLoad, [context, decor]);
  };

  /**
   * Switch list activity state.
   * @param state Flase for disabled, true for enabled state
   */
  Lister.prototype.muteList = function (state) {
    if (state) {
      $("#" + this.options.listContainerId).css({
        "opacity": 0.4,
        "cursor": "wait"
      });
    } else {
      $("#" + this.options.listContainerId).css({
        "opacity": 1,
        "cursor": "default"
      });
    }
  };

  /**
   * Lister plugin definition. Factory and API handler for Lister.
   */
  function Plugin(option) {
    return this.each(function () {
      var $this = $(this);
      var data = $this.data(LISTER_DATA_PLUGIN);

      if (!data) {
        $this.data(LISTER_DATA_PLUGIN, (data = new Lister(this, option)));
      }
      if (typeof option === "string" || option instanceof String) {
        if (this.enabled) {
          data[option].call(data);
        } else {
          $this.trigger(Lister.EVENTS.error, [this, "Lister: list is not activated"]);
        }
      }
      else if (typeof option === "object") {
        data.configure.call(data, option);
      }
    });
  }

  var old = $.fn.Lister;

  $.fn.Lister = Plugin;
  $.fn.Lister.Constructor = Lister;


  /*
   * No conflict
   */
  $.fn.Lister.noConflict = function () {
    $.fn.Lister = old;
    return this;
  };


  /*
   * API
   */
  // self initialize
  if (typeof listerListDefs === "object") {
    for (var i = 0; i < listerListDefs.length; i++) {
      var list = listerListDefs[i];
      if (typeof list.listContainerId !== "undefined") {
        $("#" + list.listContainerId).Lister(list);
      }
    }
  }
})(jQuery);