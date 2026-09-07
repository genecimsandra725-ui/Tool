(function ($) {
  "use strict";

  var COLLAPSE_KEY = "toolbox_sidebar_collapsed";
  var LIKE_PREFIX = "toolbox_liked_";

  function storageGet(key) {
    try {
      return window.localStorage.getItem(key);
    } catch (error) {
      return null;
    }
  }

  function storageSet(key, value) {
    try {
      window.localStorage.setItem(key, value);
    } catch (error) {
      // Private mode and file:// pages can throw; interaction still works in memory.
    }
  }

  function isDesktop() {
    return window.innerWidth >= 961;
  }

  $(function () {
    var $body = $("body");
    var $navToggle = $("#navToggle");
    var $backdrop = $("#sidebarBackdrop");
    var $sidebar = $("#sidebar");
    var $collapse = $("#sidebarCollapse");
    var $collapseLabel = $collapse.find("span");

    function syncCollapseButton() {
      var collapsed = $body.hasClass("sidebar-collapsed");
      $collapseLabel.text(collapsed ? "展开侧栏" : "收起侧栏");
      $collapse.attr("aria-label", collapsed ? "展开侧栏" : "折叠侧栏");
    }

    function closeMobileSidebar() {
      $body.removeClass("sidebar-open");
      $navToggle.attr("aria-expanded", "false");
      $backdrop.attr("hidden", "hidden");
    }

    if (storageGet(COLLAPSE_KEY) === "1") {
      $body.addClass("sidebar-collapsed");
    }
    syncCollapseButton();

    $navToggle.on("click", function () {
      if (isDesktop()) {
        $body.removeClass("sidebar-open");
        $backdrop.attr("hidden", "hidden");
        $body.toggleClass("sidebar-collapsed");
        storageSet(
          COLLAPSE_KEY,
          $body.hasClass("sidebar-collapsed") ? "1" : "0"
        );
        syncCollapseButton();
        return;
      }

      var open = $body.toggleClass("sidebar-open").hasClass("sidebar-open");
      $navToggle.attr("aria-expanded", open ? "true" : "false");
      if (open) {
        $backdrop.removeAttr("hidden");
      } else {
        $backdrop.attr("hidden", "hidden");
      }
    });

    $collapse.on("click", function () {
      $body.toggleClass("sidebar-collapsed");
      storageSet(
        COLLAPSE_KEY,
        $body.hasClass("sidebar-collapsed") ? "1" : "0"
      );
      syncCollapseButton();
      closeMobileSidebar();
    });

    $backdrop.on("click", closeMobileSidebar);

    $(window).on("resize", function () {
      if (isDesktop()) {
        closeMobileSidebar();
      }
    });

    $(".like-btn").each(function () {
      var $button = $(this);
      var id = $button.data("id");
      if (id && storageGet(LIKE_PREFIX + id) === "1") {
        $button.addClass("is-liked").attr("aria-pressed", "true");
      }
    });

    $(document).on("click", ".like-btn", function () {
      var $button = $(this);
      var id = $button.data("id");
      if (!$button.length || !id || $button.hasClass("is-liked")) {
        return;
      }

      $.post(
        "like.php",
        { id: id },
        function (response) {
          if (!response || !response.ok) {
            return;
          }
          $button.addClass("is-liked").attr("aria-pressed", "true");
          $button.find(".like-count").text(response.count);
          storageSet(LIKE_PREFIX + id, "1");
        },
        "json"
      );
    });
  });
})(window.jQuery);
