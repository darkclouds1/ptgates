jQuery(document).ready(function ($) {
  // Password Toggle
  $(".ptg-password-toggle").on("click", function () {
    var input = $(this).prev("input");
    var type = input.attr("type") === "password" ? "text" : "password";
    input.attr("type", type);
    $(this).toggleClass("dashicons-visibility dashicons-hidden");
  });

  // Loading State
  $("form.ptg-form").on("submit", function () {
    var btn = $(this).find('button[type="submit"]');
    if (btn.prop("disabled")) return false; // Prevent double submit

    btn.prop("disabled", true).addClass("loading");
    var originalText = btn.text();
    btn.data("text", originalText);
    btn.text("처리 중...");
  });
});
