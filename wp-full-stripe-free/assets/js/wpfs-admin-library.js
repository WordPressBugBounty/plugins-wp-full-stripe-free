/* global wpfsIsInViewport, wpfsScrollToElement, wpfsScrollToFirstFieldError, wpfsGetFieldDescriptor, wpfsFieldErrorSelector, wpfsClearFieldErrors, wpfsGlobalErrorMessages */
function initWpfsAdminLibrary($, ctx) {
  const { __ } = wp.i18n;

  const PROPERTY_NAME_BINDING_RESULT = "bindingResult";

  const ERROR_MESSAGE_FIELD_CLASS = "wpfs-form-error-message";

  const FIELD_TYPE_INPUT = "input";
  const FIELD_TYPE_INPUT_DECORATED = "input-decorated";
  const FIELD_TYPE_INPUT_CHECK_ITEM = "input-check-item";
  const FIELD_TYPE_INPUT_CUSTOM = "input-custom";
  const FIELD_TYPE_INPUT_GROUP = "input-group";
  const FIELD_TYPE_INPUT_GROUP_MINMAX = "input-group-minmax";
  const FIELD_TYPE_DROPDOWN = "dropdown";
  const FIELD_TYPE_CHECKBOX = "checkbox";
  const FIELD_TYPE_CHECKLIST = "checklist";
  const FIELD_TYPE_PRODUCTS = "products";
  const FIELD_TYPE_CARD = "card";
  const FIELD_TYPE_CAPTCHA = "captcha";
  const FIELD_TYPE_TAGS = "tags";

  ctx.Tooltip = {
    init: function () {
      $(".js-tooltip").tooltip({
        show: 300,
        items: ".js-tooltip",
        content: function () {
          var contentId = $(this).data("tooltip-content");
          return $('[data-tooltip-id="' + contentId + '"]').html();
        },
        position: {
          my: "left top+4",
          at: "left bottom+4",
          using: function (position, feedback) {
            var $this = $(this);
            $this.css(position);
            $this.addClass(feedback.vertical).addClass(feedback.horizontal);
          },
        },
        classes: {
          "ui-tooltip": "wpfs-ui wpfs-tooltip",
        },
        tooltipClass: "wpfs-ui wpfs-tooltip",
      });
    },
  };

  ctx.KeyboardKeys = {
    isEnter: function (e) {
      return e.which === 13 || e.key === "Enter" || e.code === "Enter";
    },
    isSpace: function (e) {
      return e.which === 32 || e.key === " " || e.code === "Space";
    },
    isBackspace: function (e) {
      return e.which === 8 || e.key === "Backspace" || e.code === "Backspace";
    },
    isEsc: function (e) {
      return e.which === 27 || e.key === "Escape" || e.code === "Escape";
    },
  };

  ctx.Dialog = {
    open: function (selector, options) {
      options = options || {};

      $(document.body).append('<div class="wpfs-dialog-container"></div>');

      $(selector).dialog({
        resizable: false,
        draggable: false,
        height: "auto",
        width: options.wide ? 640 : 480,
        modal: true,
        dialogClass: "wpfs-dialog" + (options.wide ? " wpfs-dialog--wide" : ""),
        closeText: "",
        appendTo: ".wpfs-dialog-container",
        closeOnEscape: false,
        open: function () {
          $(document.body).addClass("wpfs-dialog-open");

          $(".ui-widget-overlay").addClass("wpfs-dialog-overlay");

          $(this)
            .find(".js-close-this-dialog")
            .on("click.wpfs-dialog", function (e) {
              e.preventDefault();
              $(selector).dialog("close");
            });

          $(document).on("keyup.wpfs-dialog", function (e) {
            if (ctx.KeyboardKeys.isEsc(e)) {
              $(selector).dialog("close");
            }
          });
        },
        close: function () {
          $(document).off("keyup.wpfs-dialog");
          $(document.body).removeClass("wpfs-dialog-open");
          $(".wpfs-dialog-container").remove();
          $(".ui-widget-overlay").removeClass("wpfs-dialog-overlay");
          $(this).find(".js-close-this-dialog").off("click.wpfs-dialog");
        },
      });
    },
  };

  ctx.HelpDropdown = {
    init: function () {
      $(".js-open-help-dropdown").on("click", function (e) {
        e.preventDefault();
        e.stopPropagation();

        var $this = $(this);

        $this.blur();

        $(".js-help-dropdown").fadeIn(300);
        var tooltip = $this.data("ui-tooltip");
        if (tooltip) {
          tooltip.disable();
        }

        $(document.body).on("click.wpfs-help-dropdown", function (e) {
          if (!$(e.target).closest(".js-help-dropdown").length) {
            if (tooltip) {
              tooltip.enable();
            }

            $(".js-help-dropdown").fadeOut(300);

            $(document.body).off("click.wpfs-help-dropdown");
            $(document.body).off("keyup.wpfs-help-dropdown");
          }
        });

        $(document).on("keyup.wpfs-help-dropdown", function (e) {
          if (ctx.KeyboardKeys.isEsc(e)) {
            $(document.body).trigger("click");
          }
        });
      });
    },
  };

  ctx.InputGroup = {
    init: function () {
      var inputGroupPrependClass = ".wpfs-input-group-prepend";
      $(document).on("click", inputGroupPrependClass, function (e) {
        var $target = $(e.target);
        if ($target.hasClass("wpfs-input-group-link")) {
          return;
        }

        if (
          $target.parents(".wpfs-input-group-link") &&
          $target.parents(".wpfs-input-group-link").length > 0
        ) {
          return;
        }

        $(this).next().focus();
      });

      $(document).on("mouseenter", inputGroupPrependClass, function () {
        $(this).next().mouseenter();
      });

      $(document).on("mouseleave", inputGroupPrependClass, function () {
        $(this).next().mouseleave();
      });

      var inputGroupAppendClass = ".wpfs-input-group-append";
      $(document).on("click", inputGroupAppendClass, function (e) {
        var $target = $(e.target);
        if ($target.hasClass("wpfs-input-group-link")) {
          return;
        }

        if (
          $target.parents(".wpfs-input-group-link") &&
          $target.parents(".wpfs-input-group-link").length > 0
        ) {
          return;
        }

        $(this).prev().focus();
      });

      $(document).on("mouseenter", inputGroupAppendClass, function () {
        $(this).prev().mouseenter();
      });

      $(document).on("mouseleave", inputGroupAppendClass, function () {
        $(this).prev().mouseleave();
      });
    },
  };

  ctx.Selectmenu = {
    init: function () {
      $.widget("custom.wpfsSelectmenu", $.ui.selectmenu, {
        _renderItem: function (ul, item) {
          var $li = $("<li>");
          var wrapper = $("<div>", {
            class: "menu-item-wrapper ui-menu-item-wrapper",
            text: item.label,
          });

          if (item.disabled) {
            $li.addClass("ui-state-disabled");
          } else if (item.element[0].selected) {
            wrapper.addClass("ui-state-selected");
          }

          return $li.append(wrapper).appendTo(ul);
        },
      });

      var $selectmenus = $(".js-selectmenu");
      $selectmenus.each(function () {
        if (typeof $(this).select2 === "function") {
          try {
            $(this).select2("destroy");
          } catch (err) {}
        }

        var $selectmenu = $(this).wpfsSelectmenu({
          classes: {
            "ui-selectmenu-button": "wpfs-form-control wpfs-selectmenu-button",
            "ui-selectmenu-menu": "wpfs-ui wpfs-selectmenu-menu",
          },
          icons: {
            button: "wpfs-icon-chevron",
          },
          create: function () {
            var $this = $(this);
            var $selectMenuButton = $this.next();
            $selectMenuButton.addClass($this.attr("class"));
            if (
              $this.find("option:selected:disabled") &&
              $this.find("option:selected:disabled").length > 0
            ) {
              $selectMenuButton.addClass("ui-state-placeholder");
            }

            if ($(this).data("selectmenu-prefix")) {
              $selectMenuButton
                .find(".ui-selectmenu-text")
                .text(
                  $(this).data("selectmenu-prefix") + $selectMenuButton.text()
                );
            }
          },
          open: function () {
            var $this = $(this);
            var $button = $this.data("custom-wpfsSelectmenu").button;

            $button.removeClass("ui-selectmenu-button-closed");
            $button.addClass("ui-selectmenu-button-open");

            var dialogZIndex = parseInt($(".wpfs-dialog").css("zIndex"), 10);
            if (!isNaN(dialogZIndex)) {
              $(".ui-selectmenu-open").css("zIndex", dialogZIndex + 1);
            }
          },
          close: function () {
            var $this = $(this);
            var wpfsSelectmenu = $this.data("custom-wpfsSelectmenu");
            var $button = wpfsSelectmenu.button;
            $button.removeClass("ui-selectmenu-button-open");
            $button.addClass("ui-selectmenu-button-closed");

            setTimeout(function () {
              var selectedClass = "ui-state-selected";
              var selectedIndex = $this
                .find("option")
                .index($this.find("option:selected"));
              wpfsSelectmenu.menu
                .find(".ui-menu-item-wrapper")
                .removeClass(selectedClass);
              var $menuItem = wpfsSelectmenu.menu
                .find(".ui-menu-item")
                .eq(selectedIndex);
              if (!$menuItem.hasClass("ui-state-disabled")) {
                $menuItem.find(".ui-menu-item-wrapper").addClass(selectedClass);
              }
            }, 100);
          },
          change: function () {
            var $this = $(this);
            var $button = $this.data("custom-wpfsSelectmenu").button;
            if ($this.data("selectmenu-prefix")) {
              $button
                .find(".ui-selectmenu-text")
                .text($this.data("selectmenu-prefix") + $button.text());
            }
            $button.removeClass("ui-state-placeholder");
            $this.trigger("selectmenuchange");
          },
        });

        $selectmenu.on("selectmenuselect", function () {
          var $button = $(this).data("custom-wpfsSelectmenu").button;
          if ($(this).data("selectmenu-prefix")) {
            $button
              .find(".ui-selectmenu-text")
              .text($(this).data("selectmenu-prefix") + $button.text());
          }
        });

        $selectmenu
          .parent()
          .find(".ui-selectmenu-button")
          .addClass("wpfs-form-control")
          .addClass("wpfs-selectmenu-button")
          .addClass("ui-button");

        $selectmenu
          .data("custom-wpfsSelectmenu")
          .menuWrap.addClass("wpfs-ui")
          .addClass("wpfs-selectmenu-menu");
      });
    },
  };

  ctx.Combobox = {
    init: function () {
      $.widget("custom.combobox", {
        _selectOptions: [],
        _lastValidValue: null,
        _create: function () {
          this.wrapper = $("<div>")
            .addClass("wpfs-input-group wpfs-combobox")
            .addClass(this.element.attr("class"))
            .insertAfter(this.element);

          this._selectOptions = this.element
            .children("option")
            .map(function () {
              var $this = $(this);
              var text = $this.text();
              var value = $this.val();
              if (value && value !== "") {
                return {
                  label: text,
                  value: text,
                  option: this,
                };
              }
            });

          var selectedOption = this.element.find(":selected");
          if (selectedOption.length !== 0) {
            this._lastValidValue = selectedOption.val();
          }

          this.element.hide();
          this._createAutocomplete();
          this._createShowAllButton();
        },
        _createAutocomplete: function () {
          var selected = this.element.children(":selected");
          var value = selected.val() ? selected.text() : "";

          this.input = $("<input>")
            .attr("placeholder", this.element.data("placeholder"))
            .appendTo(this.wrapper)
            .val(value)
            .addClass("wpfs-input-group-form-control")
            .autocomplete({
              delay: 0,
              minLength: 0,
              source: $.proxy(this, "_source"),
              position: {
                my: "left-1px top+2.5px",
                at: "left-1px bottom+2.5px",
                using: function (position, feedback) {
                  var $this = $(this);
                  $this.css(position);
                  $this.width(feedback.target.width + 48);
                },
              },
              classes: {
                "ui-autocomplete": "wpfs-ui wpfs-combobox-menu",
              },
              open: function () {
                $(this).parent().addClass("wpfs-combobox--open");
              },
              close: function () {
                var $this = $(this);
                $this.parent().removeClass("wpfs-combobox--open");
                $this.blur();
              },
              search: function (e, ui) {
                // Fix autocomplete combobox memory leak
                $(this).data("uiAutocomplete").menu.bindings = $();
              },
            })
            .on("focus", function () {
              $(this).data("uiAutocomplete").search("");
            })
            .on("keydown", function (e) {
              if (ctx.KeyboardKeys.isEnter(e)) {
                $(this).blur();
              }
            });

          this.input.data("uiAutocomplete")._renderItem = this._renderItem;

          this._on(this.input, {
            autocompleteselect: function (e, ui) {
              if (!ui.item.noResultsItem) {
                ui.item.option.selected = true;
                this._trigger("select", e, {
                  item: ui.item.option,
                });
                this.element.trigger("comboboxchange");
              }
              this._trigger("blur");
            },
            autocompletechange: function (e, ui) {
              this._validateValue(e, ui);
              this.element.trigger("comboboxchange");
            },
          });
        },
        _createShowAllButton: function () {
          var input = this.input;
          var wasOpen = false;
          var html =
            '<div class="wpfs-input-group-append"><span class="wpfs-input-group-icon"><span class="wpfs-icon-chevron"></span></span></div>';

          $(html)
            .appendTo(this.wrapper)
            .on("mousedown", function () {
              wasOpen = input.autocomplete("widget").is(":visible");
            })
            .on("click", function (e) {
              e.stopPropagation();
              if (wasOpen) {
                input.autocomplete("close");
              } else {
                input.trigger("focus");
              }
            });
        },
        _source: function (request, response) {
          var matcher = new RegExp(
            $.ui.autocomplete.escapeRegex(request.term),
            "i"
          );
          var results = this._selectOptions.map(function (i, option) {
            if (option.value && (!request.term || matcher.test(option.label))) {
              return option;
            }
          });

          if (results && results.length > 0) {
            response(results);
          } else {
            response([
              {
                label: this.element.data("noResultsMessage"),
                value: request.term,
                noResultsItem: true,
              },
            ]);
          }
        },
        _validateValue: function (e, ui) {
          // Selected an item, nothing to do
          if (ui.item) {
            return;
          }

          // Search for a match (case-insensitive)
          var value = this.input.val();
          var valueLowerCase = value.toLowerCase();
          var valid = false;
          var selectedText = null;
          this.element.children("option").each(function () {
            var text = $(this).text();
            if (text.toLowerCase() === valueLowerCase) {
              selectedText = text;
              this.selected = valid = true;
              return false;
            }
          });

          if (valid) {
            // Fix valid value
            this.input.val(selectedText);
            this._lastValidValue = selectedText;
          } else if (!valid && this._lastValidValue !== null) {
            // Set last valid value
            this.input.val(this._lastValidValue);
          } else {
            // Remove invalid value
            this.input.val("");
            this.element.val("");
            this.input.autocomplete("instance").term = "";
          }
        },
        _renderItem: function (ul, item) {
          var t = "";
          var idx = item.label.toLowerCase().indexOf(this.term.toLowerCase());
          var sameLabelAndTerm =
            item.label.toLowerCase() === this.term.toLowerCase();

          if (idx !== -1 && !sameLabelAndTerm && this.term !== "") {
            var termLength = this.term.length;
            t += item.label.substring(0, idx);
            t += "<strong>" + item.label.substr(idx, termLength) + "</strong>";
            t += item.label.substr(idx + termLength);
          } else {
            t = item.label;
          }

          var $li = $("<li></li>");
          var $div = $('<div class="ui-menu-item-wrapper">' + t + "</div>");
          if (!item.noResultsItem) {
            $li.data("item.autocomplete", item);
            if (sameLabelAndTerm) {
              $div.addClass("ui-state-selected");
            }
          } else {
            $li.addClass("ui-state-disabled");
          }

          ul.addClass("wpfs-ui").addClass("wpfs-combobox-menu");

          return $li.append($div).appendTo(ul);
        },
        _destroy: function () {
          this.wrapper.remove();
          this.element.show();
        },
        refresh: function () {
          this._destroy();
          this._create();
        },
      });

      $(".js-combobox").combobox();
    },
  };

  ctx.FlashMessage = {
    init: function () {
      $(document.body).on("click", ".js-hide-flash-message", function (e) {
        e.preventDefault();
        var $flashMessage = $(this).parents(".js-flash-message");
        $flashMessage.removeClass("wpfs-floating-message--show");
      });
    },
    close: function (delay) {
      setTimeout(function () {
        $(".js-flash-message").removeClass("wpfs-floating-message--show");
      }, delay || 3000);
    },
  };

  ctx.InlineMessage = {
    init: function () {
      $(document).on("click", ".js-close-this-message", function (e) {
        e.preventDefault();
        $(this)
          .parents(".js-inline-message")
          .addClass("wpfs-inline-message--hide");
      });
    },
  };

  ctx.FormSearch = {
    init: function () {
      function checkInput(el) {
        if ($(el).val() !== "") {
          $(el).addClass("wpfs-form-search__input--active");
        } else {
          $(el).removeClass("wpfs-form-search__input--active");
        }
      }

      var $formSearchEls = $(".js-form-search");
      $formSearchEls.each(function () {
        var $searchInput = $(this).find("input");
        checkInput($searchInput);
        $searchInput.on("change", function () {
          checkInput(this);
        });
      });
    },
  };

  ctx.SidePane = {
    init: function () {
      $(".js-open-side-pane").on("click", function (e) {
        e.preventDefault();
        var tooltip = $(this).data("ui-tooltip");
        if (tooltip) {
          tooltip.close();
        }
        var sidePaneId = $(this).data("side-pane-id");
        ctx.SidePane.open(sidePaneId);
      });

      $(".js-close-side-pane").on("click", function (e) {
        e.preventDefault();
        ctx.SidePane.close();
      });

      $(".js-side-pane").on("click", function (e) {
        if (!$(e.target).closest(".wpfs-side-pane").length) {
          e.preventDefault();
          ctx.SidePane.close();
        }
      });
    },
    open: function (sidePaneId) {
      $('[data-side-pane-id="' + sidePaneId + '"]').addClass(
        "wpfs-side-pane-overlay--show"
      );
      $(document).on("keyup.wpfs-side-pane", function (e) {
        if (ctx.KeyboardKeys.isEsc(e) && $(".wpfs-dialog-open").length === 0) {
          ctx.SidePane.close();
        }
      });
      $(document.body).addClass("wpfs-side-pane-open");
    },
    close: function () {
      $(".wpfs-side-pane-overlay--show").removeClass(
        "wpfs-side-pane-overlay--show"
      );
      $(document).off("keyup.wpfs-side-pane");
      $(document.body).removeClass("wpfs-side-pane-open");
    },
  };

  ctx.Controls = {
    init: function () {
      $(".js-reset-controls").on("click", function (e) {
        e.preventDefault();
        var $parent = $(this).parent();
        var $input = $parent.find("input");

        $input.val("");
        $input.trigger("change");

        $parent.find("select").each(function () {
          $(this).prop("selectedIndex", 0);
          $(this).wpfsSelectmenu("refresh").trigger("selectmenuselect");
        });
      });
    },
  };

  ctx.Carousel = {
    init: function () {
      $(".js-carousel").slick({
        prevArrow:
          '<button type="button" class="slick-prev"><div class="wpfs-icon-arrow-left"></div></button>',
        nextArrow:
          '<button type="button" class="slick-next"><div class="wpfs-icon-arrow-left"></div></button>',
        dots: true,
      });
    },
  };

  ctx.CodeEditor = {
    editors: [],
    init: function () {
      $(".js-code-editor").each(function () {
        this.value = this.value
          .split("\n")
          .map(function (row) {
            return row.trim();
          })
          .join("\n");

        this.value = css_beautify(this.value, {
          indent_size: "2",
        });

        var editor = null;
        if (wpfsAdminSettings.codeEditorSettings) {
          editor = wp.codeEditor.initialize(this, wpfsAdminSettings.codeEditorSettings);
        }
        ctx.CodeEditor.editors.push({ textarea: this, editor: editor });
      });
    },
    getEditorValue: function (idx) {
      var entry = ctx.CodeEditor.editors[idx];
      return entry.editor ? entry.editor.codemirror.getValue() : entry.textarea.value;
    },
  };

  ctx.ToPascalCase = {
    init: function () {
      $(".js-to-pascal-case").on("blur", function () {
        var $this = $(this);
        var $input = $($this.data("to-pascal-case"));

        if ($this.val() !== "" && $input.val().trim() === "") {
          if ($input.length > 0) {
            $input.val(ctx.ToPascalCase.get($this.val()));
          }
        }
      });
    },
    get: function (str) {
      const fastHash = (s) => {
        let hash = 1000;
        for (let i = 0; i < s.length; i++) {
          hash = (hash << 5) - hash + s.charCodeAt(i);
          hash |= 0;
        }
        return Math.abs(hash).toString(36);
      }
      const identifier = str
        .match(/[a-z]+/gi)
        ?.map(function (word) {
          return word.charAt(0).toUpperCase() + word.substring(1).toLowerCase();
        })
        .join("") ?? '';

      if (!identifier) {
        return `form_${fastHash(str)}`;
      }

      return identifier;
    },
  };

  ctx.TagsInput = {
    tagTemplate:
      '<div class="wpfs-tag wpfs-tag--removable">{{tag}}<button class="wpfs-btn wpfs-btn-icon wpfs-btn-icon--12 wpfs-tag__remove js-remove-tag"><span class="wpfs-icon-close"></span></button></div>',
    generateItem: function (element) {
      var template = ctx.TagsInput.tagTemplate.replace(
        "{{tag}}",
        element.val().trim()
      );
      $(template).insertBefore(element);
      element.val("");
    },
    init: function () {
      var self = this;
      $(".js-tags-input input").on({
        keydown: function (e) {
          var $this = $(this);
          if (
            (ctx.KeyboardKeys.isEnter(e) || ctx.KeyboardKeys.isSpace(e)) &&
            $this.val().trim() !== ""
          ) {
            e.preventDefault();
            self.generateItem($this);
          }

          if (ctx.KeyboardKeys.isBackspace(e) && $this.val() === "") {
            var $parent = $this.parent();
            $parent.find(".wpfs-tag:last").remove();
          }
        },
        blur: function () {
          var $this = $(this);
          if ($this.val().trim() !== "") {
            self.generateItem($this);
          }
        },
      });

      $(document).on("click", ".js-remove-tag", function (e) {
        e.preventDefault();
        var $this = $(this);
        if ($this.hasClass("wpfs-tag")) {
          $(this).remove();
        } else {
          $(this).parents(".wpfs-tag").remove();
        }
      });
    },
  };

  ctx.Toggler = {
    init: function () {
      $(".wpfs-toggler input[type=checkbox]").on("change", function () {
        var $parent = $(this).parent();
        if ($(this).is(":checked")) {
          $parent.addClass("wpfs-toggler--checked");
        } else {
          $parent.removeClass("wpfs-toggler--checked");
        }
      });
      $(".wpfs-toggler input[type=checkbox]:checked")
        .parent()
        .addClass("wpfs-toggler--checked");
    },
  };

  ctx.Sortable = {
    init: function () {
      $(".js-sortable").sortable();
      $(".js-sortable").disableSelection();
    },
  };

  ctx.Datepicker = {
    init: function () {
      $(".js-datepicker").each(function () {
        var $this = $(this);
        var dateFormat = $this.data("dateFormat") || "dd/mm/yyyy";
        var defaultValue = $this.data("defaultValue") || "";

        if ($this.val() === "") {
          $this.val(defaultValue);
        }

        if ($this.next().hasClass("wpfs-form-search__btn")) {
          $this.next().on("click", function (e) {
            e.preventDefault();
            $this.trigger("focus");
          });
        }

        $this.datepicker({
          prevText: "",
          nextText: "",
          hideIfNoPrevNext: true,
          firstDay: 1,
          dateFormat: dateFormat.replace(/yy/g, "y"),
          showOtherMonths: true,
          selectOtherMonths: true,
          onChangeMonthYear: function (year, month, inst) {
            if (inst.dpDiv.hasClass("bottom")) {
              setTimeout(function () {
                inst.dpDiv.css(
                  "top",
                  inst.input.offset().top - inst.dpDiv.outerHeight()
                );
              });
            }
          },
          beforeShow: function (el, inst) {
            var $el = $(el);
            inst.dpDiv.addClass("wpfs-ui wpfs-datepicker-div");
            setTimeout(function () {
              if ($el.offset().top > inst.dpDiv.offset().top) {
                inst.dpDiv.removeClass("top");
                inst.dpDiv.addClass("bottom");
              } else {
                inst.dpDiv.removeClass("bottom");
                inst.dpDiv.addClass("top");
              }
            });
          },
        });
      });
    },
  };

  ctx.isInViewport = wpfsIsInViewport;

  ctx.scrollToElement = wpfsScrollToElement;

  ctx.initSuccessMessageBanner = function () {
    if ($("#wpfs-success-message") && $("#wpfs-success-message").length > 0) {
      ctx.SuccessMessageView = Backbone.View.extend({
        className:
          "wpfs-floating-message wpfs-floating-message--success wpfs-floating-message--show js-flash-message",
        template: _.template($("#wpfs-success-message").html()),
        render: function () {
          this.$el.html(this.template(this.model.attributes));
          return this;
        },
      });
      ctx.SuccessMessageModel = Backbone.Model.extend({});
    }
  };

  ctx.displaySuccessMessageBanner = function (message) {
    var successMessageModel = new ctx.SuccessMessageModel({
      successMessage: message,
    });
    var successMessageView = new ctx.SuccessMessageView({
      model: successMessageModel,
    });

    $("#wpfs-success-message-container")
      .empty()
      .append(successMessageView.render().el);
    ctx.FlashMessage.init();
    ctx.FlashMessage.close();
  };

  ctx.getGlobalMessageContainer = function ($form, title, message) {
    var $messageContainer = $(".wpfs-form-message", $form);
    if (0 === $messageContainer.length) {
      $form.prepend(
        '<div class="wpfs-form-message wpfs-form-message--mb">' +
          '<div class="wpfs-form-message__inner">' +
          '<div class="wpfs-form-message__title">' +
          title +
          "</div>" +
          "<p>" +
          message +
          "</p>" +
          "</div>" +
          "</div>"
      );
      $messageContainer = $(".wpfs-form-message", $form);
    }

    return $messageContainer;
  };

  ctx.__showGlobalMessage = function ($form, title, message) {
    return ctx.getGlobalMessageContainer($form, title, message);
  };

  ctx.clearGlobalMessage = function ($form) {
    var $messageContainer = ctx.getGlobalMessageContainer($form, "", "");
    $messageContainer.remove();
  };

  ctx.clearFieldErrors = wpfsClearFieldErrors;

  ctx.clearFormErrors = function ($form) {
    ctx.clearGlobalMessage($form);
    ctx.clearFieldErrors($form);
  };

  ctx.showButtonLoader = function ($form) {
    if ($form) {
      $form
        .find(".wpfs-button-loader")
        .addClass("wpfs-btn-primary--loader")
        .prop("disabled", true);
    } else {
      $(".wpfs-button-loader")
        .addClass("wpfs-btn-primary--loader")
        .prop("disabled", true);
    }
  };

  ctx.hideButtonLoader = function ($form) {
    if ($form) {
      $form
        .find(".wpfs-button-loader")
        .removeClass("wpfs-btn-primary--loader")
        .prop("disabled", false);
    } else {
      $(".wpfs-button-loader")
        .removeClass("wpfs-btn-primary--loader")
        .prop("disabled", false);
    }
  };

  ctx.showErrorGlobalMessage = function ($form, messageTitle, message) {
    var $globalMessageContainer = ctx.__showGlobalMessage(
      $form,
      messageTitle,
      message
    );
    $globalMessageContainer.addClass("wpfs-form-message--incorrect");
    ctx.scrollToElement($globalMessageContainer, false);
  };

  ctx.getFormType = function ($form) {
    return $form.data("wpfs-form-type");
  };

  ctx.getFieldDescriptor = function (formType, fieldName) {
    return wpfsGetFieldDescriptor(wpfsFormFields, formType, fieldName);
  };

  ctx.showFieldError = function ($form, field, fieldDescriptor, scrollTo) {
    if (ctx.debugLog) {
      logInfo("showFieldError.field: ", JSON.stringify(field));
      logInfo("showFieldError.descriptor: ", JSON.stringify(fieldDescriptor));
    }
    if (fieldDescriptor != null) {
      var fieldType = fieldDescriptor.type;
      var fieldClass = fieldDescriptor.class;
      var fieldSelector = fieldDescriptor.selector;
      var fieldErrorClass = fieldDescriptor.errorClass;
      var fieldErrorSelector = wpfsFieldErrorSelector(
        fieldDescriptor,
        field.id
      );

      // tnagy initialize field
      var theFieldSelector;
      if (field.id != null) {
        theFieldSelector = "#" + field.id;
      } else {
        theFieldSelector = fieldSelector;
      }
      var $field = $(theFieldSelector, $form);

      // tnagy create error message
      var $fieldError = $("<div>", {
        class: ERROR_MESSAGE_FIELD_CLASS,
        "data-wpfs-field-error-for": field.id,
      }).html(field.message);

      // tnagy add error class, insert error message
      if (FIELD_TYPE_INPUT === fieldType) {
        if (fieldErrorSelector != null) {
          $field.closest(fieldErrorSelector).addClass(fieldErrorClass);
        }
        $fieldError.insertAfter($field);
      } else if (FIELD_TYPE_INPUT_DECORATED === fieldType) {
        if (fieldErrorSelector != null) {
          $field.closest(fieldErrorSelector).addClass(fieldErrorClass);
        }
        $fieldError.appendTo($field.parent());
      } else if (FIELD_TYPE_INPUT_CHECK_ITEM === fieldType) {
        if (fieldErrorSelector != null) {
          $field.closest(fieldErrorSelector).addClass(fieldErrorClass);
        }
        $fieldError.appendTo($field.parent().parent());
      } else if (FIELD_TYPE_INPUT_GROUP === fieldType) {
        if (fieldErrorSelector != null) {
          $field.closest(fieldErrorSelector).addClass(fieldErrorClass);
        }
        $fieldError.insertAfter($field.closest(fieldErrorSelector));
      } else if (FIELD_TYPE_INPUT_GROUP_MINMAX === fieldType) {
        if (fieldErrorSelector != null) {
          $field.closest(fieldErrorSelector).addClass(fieldErrorClass);
        }
        $fieldError.appendTo($field.parent().parent().parent());
      } else if (FIELD_TYPE_INPUT_CUSTOM === fieldType) {
        if (fieldErrorSelector != null) {
          $field.closest(fieldErrorSelector).addClass(fieldErrorClass);
        }
        $fieldError.insertAfter($field);
      } else if (FIELD_TYPE_DROPDOWN === fieldType) {
        if (fieldErrorSelector != null) {
          $field.closest("." + fieldClass).addClass(fieldErrorClass);
          $(fieldErrorSelector).addClass(fieldErrorClass);
        }
        $fieldError.appendTo($field.parent());
      } else if (FIELD_TYPE_CHECKBOX === fieldType) {
        if (fieldErrorSelector != null) {
          $field.closest(fieldErrorSelector).addClass(fieldErrorClass);
        }
        $fieldError.appendTo($field.parent());
      } else if (FIELD_TYPE_CHECKLIST === fieldType) {
        $fieldError.insertAfter($field);
      } else if (FIELD_TYPE_PRODUCTS === fieldType) {
        if (fieldErrorSelector != null) {
          $(fieldErrorSelector).addClass(fieldErrorClass);
        }
        $fieldError.insertAfter($field);
      } else if (FIELD_TYPE_TAGS === fieldType) {
        if (fieldErrorSelector != null) {
          $field.closest(fieldErrorSelector).addClass(fieldErrorClass);
        }
        $fieldError.appendTo($field.parent().parent());
      }

      if (typeof scrollTo != "undefined") {
        if (scrollTo) {
          ctx.scrollToElement($field, false);
        }
      }
    } else {
      logInfo("showFieldError", "FieldDescription not found!");
    }
  };

  ctx.showFormError = function ($form, field, errorTitle, scrollTo) {
    var formType = ctx.getFormType($form);
    var fieldDescriptor = ctx.getFieldDescriptor(formType, field.name);
    if (fieldDescriptor != null) {
      if (true === fieldDescriptor.hidden) {
        ctx.showErrorGlobalMessage($form, errorTitle, field.message);
      } else {
        ctx.showFieldError($form, field, fieldDescriptor, scrollTo);
      }
    }
  };

  ctx.showFormTab = function (tabId) {
    $(".wpfs-edit-form-pane").hide();
    var $pane = $('.wpfs-edit-form-pane[data-tab-id="' + tabId + '"]').show();
    $pane.find(".CodeMirror").each(function () {
      this.CodeMirror.refresh();
    });
  };

  ctx.activateTabSelector = function (tabId) {
    $(".wpfs-form-tab").removeClass("wpfs-page-tabs__item--active");
    $('.wpfs-form-tab[data-tab-id="' + tabId + '"]').addClass(
      "wpfs-page-tabs__item--active"
    );
  };

  ctx.showFirstErrorTab = function ($form) {
    var firstErrorFieldId = $(".wpfs-form-error-message", $form)
      .first()
      .data("wpfs-field-error-for");

    var $formPane = $("#" + firstErrorFieldId).closest(".wpfs-edit-form-pane");
    if ($formPane.length === 1) {
      var tabId = $formPane.data("tab-id");

      ctx.activateTabSelector(tabId);
      ctx.showFormTab(tabId);
    }
  };

  ctx.scrollToFirstFieldError = wpfsScrollToFirstFieldError;

  ctx.createGlobalErrorMessage = wpfsGlobalErrorMessages;

  ctx.processValidationErrors = function ($form, bindingResult) {
    if (bindingResult) {
      if (bindingResult.fieldErrors && bindingResult.fieldErrors.errors) {
        var fieldErrors = bindingResult.fieldErrors.errors;
        for (var index in fieldErrors) {
          var fieldError = fieldErrors[index];
          ctx.showFormError($form, fieldError, bindingResult.fieldErrors.title);
        }
        ctx.showFirstErrorTab($form);
        ctx.scrollToFirstFieldError($form);
      }

      if (bindingResult.globalErrors && bindingResult.globalErrors.errors) {
        var globalErrorMessages = ctx.createGlobalErrorMessage(
          bindingResult.globalErrors
        );
        if ("" !== globalErrorMessages) {
          ctx.showErrorGlobalMessage(
            $form,
            bindingResult.globalErrors.title,
            globalErrorMessages
          );
        }
      }
    }
  };

  ctx.makeAddStripeAccountAjaxCall = function ($accountId, $mode, $connectToken, $connectCode) {
    if (ctx.debugLog) {
      console.log(
        "[makeAddStripeAccountAjaxCall] calling ajax with nonce: " +
          wpfsAdminSettings.nonce
      );
    }
    $.ajax({
      type: "POST",
      url: wpfsAdminSettings.ajaxUrl,
      data: {
        action: "wpfs-add-stripe-account",
        account_id: $accountId,
        mode: $mode,
        connect_token: $connectToken,
        connect_code: $connectCode || "",
        nonce: wpfsAdminSettings.nonce,
      },
      cache: false,
      dataType: "json",
      success: function (responseData) {
        // A refused account goes to the notice that says why.
        if (responseData && !responseData.success && responseData.redirectURL) {
          ctx.setTimeoutToRedirect(responseData.redirectURL, 0);
          return;
        }

        var currentUrl = new URL( window.location.href );
        var stripeUrl = new URL( window.wpfsAdminSettings.connectionPage );

        if ( currentUrl.searchParams.has( "onboarding" ) || ( responseData && responseData.onboarding ) ) {
          stripeUrl.searchParams.set( "onboarding", "true" );
        }

        ctx.setTimeoutToRedirect( stripeUrl.toString(), 0 );
      },
    });
  };

  ctx.makeConfirmStripeConnectionAjaxCall = function ($mode) {
    $(".wpfs-button-loader")
      .addClass("wpfs-btn-primary--loader")
      .prop("disabled", true);
    $.ajax({
      type: "POST",
      url: wpfsAdminSettings.ajaxUrl,
      data: {
        action: "wpfs-confirm-stripe-connection",
        current_page_url: window.location.href,
        mode: $mode,
        nonce: wpfsAdminSettings.nonce,
      },
      cache: false,
      dataType: "json",
      success: function (responseData) {
        if (responseData.redirectURL) {
          ctx.setTimeoutToRedirect(responseData.redirectURL, 0);
          return;
        }
        $(".wpfs-button-loader")
          .removeClass("wpfs-btn-primary--loader")
          .prop("disabled", false);
        if (responseData.msg) {
          window.alert(responseData.msg);
        }
      },
      error: function (jqXHR, textStatus, errorThrown) {
        logError(
          "wpfs-admin.makeConfirmStripeConnectionAjaxCall()",
          jqXHR,
          textStatus,
          errorThrown
        );
        // A refused or failed request, for example after the admin's session expired, can be tried again.
        $(".wpfs-button-loader")
          .removeClass("wpfs-btn-primary--loader")
          .prop("disabled", false);
        var message =
          jqXHR.responseJSON &&
          jqXHR.responseJSON.data &&
          jqXHR.responseJSON.data.message;
        // Without the plugin's reason, the request may still have confirmed the connection, so a reload shows where it stands.
        window.alert(
          message ||
            __(
              "Your Stripe connection could not be confirmed. Please reload the page and try again.",
              "wp-full-stripe-free"
            )
        );
      },
    });
  };

  ctx.makeCreateStripeConnectAccountAjaxCall = function ($mode) {
    $(".wpfs-button-loader")
      .addClass("wpfs-btn-primary--loader")
      .prop("disabled", true);
    if (ctx.debugLog) {
      console.log(
        "[makeCreateStripeConnectAccountAjaxCall] calling ajax with nonce: " +
          wpfsAdminSettings.nonce
      );
    }
    $.ajax({
      type: "POST",
      url: wpfsAdminSettings.ajaxUrl,
      data: {
        action: "wpfs-create-stripe-connect-account",
        current_page_url: window.location.href,
        mode: $mode,
        nonce: wpfsAdminSettings.nonce,
      },
      cache: false,
      dataType: "json",
      success: function (responseData) {
        // A refused start also answers with a URL: the notice that says why.
        if (responseData.redirectURL) {
          ctx.setTimeoutToRedirect(responseData.redirectURL, 0);
        }
      },
      error: function (jqXHR, textStatus, errorThrown) {
        logError(
          "wpfs-admin.makeCreateStripeConnectAccountAjaxCall()",
          jqXHR,
          textStatus,
          errorThrown
        );
      },
      complete: function () {},
    });
  };

  // AJAX call to clear stripe settings
  ctx.makeClearStripeSettingsAjaxCall = function ($mode) {
    $(".wpfs-button-loader")
      .addClass("wpfs-btn-primary--loader")
      .prop("disabled", true);
    if (ctx.debugLog) {
      console.log(
        "[makeClearStripeSettingsAjaxCall] calling ajax with nonce: " +
          wpfsAdminSettings.nonce
      );
    }
    $.ajax({
      type: "POST",
      url: wpfsAdminSettings.ajaxUrl,
      data: {
        action: "wpfs-clear-stripe-settings",
        mode: $mode,
        nonce: wpfsAdminSettings.nonce,
      },
      cache: false,
      dataType: "json",
      success: function (responseData) {
        if (responseData.success) {
          ctx.displaySuccessMessageBanner(responseData.msg);
          ctx.setTimeoutToRedirect(responseData.redirectURL, 1000);
        } else {
          // Disconnect was refused, for example while another request changed the connection.
          $(".wpfs-button-loader")
            .removeClass("wpfs-btn-primary--loader")
            .prop("disabled", false);
          window.alert(responseData.msg);
        }
      },
      error: function (jqXHR, textStatus, errorThrown) {
        logError(
          "wpfs-admin.makeClearStripeSettingsAjaxCall()",
          jqXHR,
          textStatus,
          errorThrown
        );
      },
      complete: function () {},
    });
  };

  ctx.makeAjaxCallWithForm = function ($form) {
    ctx.clearFormErrors($form);
    ctx.showButtonLoader($form);
    if (ctx.debugLog) {
      console.log(
        "[makeAjaxCallWithForm] calling ajax with nonce: " +
          wpfsAdminSettings.nonce
      );
    }
    $form.append(
      '<input type="hidden" name="nonce" value="' +
        wpfsAdminSettings.nonce +
        '" />'
    );
    $.ajax({
      type: "POST",
      url: wpfsAdminSettings.ajaxUrl,
      data: $form.serialize(),
      cache: false,
      dataType: "json",
      success: function (responseData) {
        if (responseData.success) {
          ctx.setFormSaved();
          ctx.displaySuccessMessageBanner(responseData.msg);
          ctx.setTimeoutToRedirect(responseData.redirectURL, 1000);
        } else {
          if (responseData.hasOwnProperty(PROPERTY_NAME_BINDING_RESULT)) {
            ctx.processValidationErrors($form, responseData.bindingResult);
          } else {
            ctx.showErrorGlobalMessage(
              $form,
              /* translators: Error banner title for internal, unexpected errors/problems */
              __("Internal error", "wp-full-stripe-free"),
              responseData.msg
            );
          }
        }
      },
      error: function (jqXHR, textStatus, errorThrown) {
        logError(
          "wpfs-admin.makeAjaxCallWithForm()",
          jqXHR,
          textStatus,
          errorThrown
        );
      },
      complete: function () {
        ctx.hideButtonLoader($form);
      },
    });
  };

  ctx.createFieldDescriptor = function (
    type,
    name,
    clazz,
    selector,
    errorClass,
    errorSelector,
    hidden = false
  ) {
    return {
      type: type,
      name: name,
      class: clazz,
      selector: selector,
      errorClass: errorClass,
      errorSelector: errorSelector,
      hidden: hidden,
    };
  };

  ctx.createInputDecoratedDescriptor = function (name) {
    var clazz = "wpfs-form-control";
    var selector = "." + clazz + "[name='" + name + "']";
    var errorClass = "wpfs-form-control--error";
    var errorSelector = "." + clazz;

    return ctx.createFieldDescriptor(
      FIELD_TYPE_INPUT_DECORATED,
      name,
      clazz,
      selector,
      errorClass,
      errorSelector
    );
  };

  ctx.createInputCheckItemDescriptor = function (name) {
    var clazz = "wpfs-form-control";
    var selector = "." + clazz + "[name='" + name + "']";
    var errorClass = "wpfs-form-control--error";
    var errorSelector = "." + clazz;

    return ctx.createFieldDescriptor(
      FIELD_TYPE_INPUT_CHECK_ITEM,
      name,
      clazz,
      selector,
      errorClass,
      errorSelector
    );
  };

  ctx.createInputGroupDescriptor = function (name) {
    var clazz = "wpfs-input-group-form-control";
    var selector = "." + clazz + "[name='" + name + "']";
    var errorClass = "wpfs-input-group--error";
    var errorSelector = "." + "wpfs-input-group";

    return ctx.createFieldDescriptor(
      FIELD_TYPE_INPUT_GROUP,
      name,
      clazz,
      selector,
      errorClass,
      errorSelector
    );
  };

  ctx.createInputTags = function (name) {
    var clazz = "wpfs-tags-input";
    var selector = "." + clazz + "[name='" + name + "']";
    var errorClass = "wpfs-form-control--error";
    var errorSelector = "." + clazz;

    return ctx.createFieldDescriptor(
      FIELD_TYPE_TAGS,
      name,
      clazz,
      selector,
      errorClass,
      errorSelector
    );
  };

  ctx.logException = function (source, response) {
    if (window.console && response) {
      if (response.ex_msg) {
        console.log("ERROR: source=" + source + ", message=" + response.ex_msg);
      }
      if (response.ex_stack) {
        console.log("ERROR: source=" + source + ", stack=" + response.ex_stack);
      }
    }
  };

  ctx.copyToClipboard = function (str) {
    var el = document.createElement("textarea");
    el.value = str;
    el.setAttribute("readonly", "");
    el.style.position = "absolute";
    el.style.left = "-9999px";
    document.body.appendChild(el);
    var selected =
      document.getSelection().rangeCount > 0
        ? document.getSelection().getRangeAt(0)
        : false;
    el.select();
    document.execCommand("copy");
    document.body.removeChild(el);
    if (selected) {
      document.getSelection().removeAllRanges();
      document.getSelection().addRange(selected);
    }
  };

  ctx.createAdminCurrencyFormatter = function () {
    var decimalSeparator =
      wpfsAdminSettings.preferences.currencyDecimalSeparatorSymbol;
    var showCurrencySymbolInsteadOfCode =
      wpfsAdminSettings.preferences.currencyShowSymbolInsteadOfCode;
    var showCurrencySignAtFirstPosition =
      wpfsAdminSettings.preferences.currencyShowIdentifierOnLeft;
    var putWhitespaceBetweenCurrencyAndAmount =
      wpfsAdminSettings.preferences.currencyPutSpaceBetweenCurrencyAndAmount;

    return WPFSCurrencyFormatter(
      decimalSeparator,
      showCurrencySymbolInsteadOfCode,
      showCurrencySignAtFirstPosition,
      putWhitespaceBetweenCurrencyAndAmount
    );
  };

  ctx.setTimeoutToRedirect = function (redirectUrl, timeout) {
    setTimeout(function () {
      window.location = redirectUrl;
    }, timeout);
  };

  ctx.setFormSaved = function() {
    $(document).trigger('wpfs-form-saved');
  }
}

/**
 * FormHeaderButtonMirror - Automatically mirrors form action buttons to page header
 * 
 * This class detects form submit buttons in .wpfs-form-actions and
 * clones them to .wpfs-page-header__actions. Clicking either button triggers
 * the original form submit button, maintaining all existing form processing logic.
 * 
 * The mirror button syncs its loading state with the original button by watching
 * for the wpfs-btn-primary--loader class that gets added/removed during AJAX calls.
 */
class FormHeaderButtonMirror {
  constructor() {
    this.formActions = document.querySelector('.wpfs-form-actions');
    this.headerActions = document.querySelector('.wpfs-page-header__actions');
    this.submitButton = null;
    this.mirrorButton = null;
    this.observer = null;

    if (!this.formActions || !this.headerActions) {
      return;
    }

    this.init();
  }

  /**
   * Initialize the mirror
   */
  init() {
    this.submitButton = this.formActions.querySelector('button[type="submit"]');
    
    if (!this.submitButton) {
      return;
    }

    this.createMirrorButton();
    this.observeOriginalButton();
  }

  /**
   * Create a mirror button in the header
   */
  createMirrorButton() {
    this.mirrorButton = this.submitButton.cloneNode(true);
    
    this.mirrorButton.classList.add('wpfs-header-btn-primary');
    
    // Bind click to original button submit
    this.mirrorButton.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      this.submitButton.click();
    });

    this.headerActions.appendChild(this.mirrorButton);
  }

  /**
   * Sync loading state from original button to mirror button
   * The loading state is controlled by wpfs-btn-primary--loader class
   * and the disabled attribute
   */
  syncLoadingState() {
    const hasLoading = this.submitButton.classList.contains('wpfs-btn-primary--loader');
    const isDisabled = this.submitButton.disabled;
    
    if (hasLoading) {
      this.mirrorButton.classList.add('wpfs-btn-primary--loader');
    } else {
      this.mirrorButton.classList.remove('wpfs-btn-primary--loader');
    }
    
    this.mirrorButton.disabled = isDisabled;
  }

  /**
   * Observe the original submit button for loading state changes
   * When the original button's loading class changes, sync it to mirror
   */
  observeOriginalButton() {
    this.observer = new MutationObserver(() => {
      this.syncLoadingState();
    });

    this.observer.observe(this.submitButton, {
      attributes: true,
      attributeFilter: ['class', 'disabled']
    });
  }
}

// Auto-initialize on DOM ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    new FormHeaderButtonMirror();
  });
} else {
  new FormHeaderButtonMirror();
}
