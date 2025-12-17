jQuery(document).ready(function ($) {
  var $app = $("#ptg-product-manager-app");
  var $listBody = $("#ptg-product-list-body");
  var $modal = $("#ptg-product-modal");
  var $form = $("#ptg-product-form");

  // Initial Load
  loadProducts();

  // Event Listeners
  $("#ptg-add-product-btn").on("click", function () {
    openModal("create");
  });

  $(".ptg-modal-close, #ptg-modal-cancel").on("click", function () {
    closeModal();
  });

  $("#ptg-save-product-btn").on("click", function () {
    saveProduct();
  });

  // Dynamic Events for List Items
  $listBody.on("click", ".ptg-edit-btn", function () {
    var product = $(this).data("product");
    openModal("edit", product);
  });

  $listBody.on("click", ".ptg-delete-btn", function () {
    var id = $(this).data("id");
    if (confirm("정말로 이 상품을 삭제하시겠습니까?")) {
      deleteProduct(id);
    }
  });

  $listBody.on("change", ".ptg-status-toggle", function () {
    var id = $(this).data("id");
    var status = $(this).is(":checked") ? 1 : 0;
    toggleStatus(id, status);
  });

  // --- Functions ---

  function loadProducts() {
    $(".ptg-loading").show();

    $.ajax({
      url: ptgAdmin.restUrl + "products", // or use admin-ajax
      // We implemented it via admin-ajax in class-admin-products.php, let's use that.
      // Wait, I implemented standard WP AJAX actions: ptg_admin_get_products
      // But ptgAdmin script data has 'ajaxUrl'.

      url: ptgAdmin.ajaxUrl,
      method: "POST",
      data: {
        action: "ptg_admin_get_products",
        security: ptgAdmin.nonce,
      },
      success: function (response) {
        $(".ptg-loading").hide();
        if (response.success) {
          renderList(response.data);
        } else {
          alert("데이터 로드 실패: " + response.data);
        }
      },
      error: function () {
        $(".ptg-loading").hide();
        alert("서버 통신 오류");
      },
    });
  }

  function renderList(products) {
    $listBody.empty();

    if (products.length === 0) {
      $listBody.append(
        '<tr><td colspan="10" style="text-align:center; padding: 20px;">등록된 상품이 없습니다.</td></tr>'
      );
      return;
    }

    products.forEach(function (item) {
      var featuresPreview = item.features ? item.features.join(", ") : "";
      if (featuresPreview.length > 20)
        featuresPreview = featuresPreview.substring(0, 20) + "...";

      var statusBadge =
        item.is_active == 1
          ? '<span class="status-badge status-active">활성</span>'
          : '<span class="status-badge status-inactive">비활성</span>';

      var row = `
                <tr>
                    <th scope="row" class="check-column"><input type="checkbox" name="product[]" value="${
                      item.id
                    }"></th>
                    <td>${item.id}</td>
                    <td><strong>${item.product_code}</strong></td>
                    <td>
                        <strong>${item.title}</strong>
                        <div class="row-actions">
                            <span class="edit"><a href="#" class="ptg-edit-btn" data-product='${JSON.stringify(
                              item
                            )}'>편집</a> | </span>
                            <span class="trash"><a href="#" class="ptg-delete-btn" data-id="${
                              item.id
                            }" style="color: #a00;">삭제</a></span>
                        </div>
                    </td>
                    <td>${item.duration_months}개월</td>
                    <td>${Number(
                      item.price
                    ).toLocaleString()}원<br><small style="color:#666">${
        item.price_label || ""
      }</small></td>
                    <td>${
                      item.featured_level > 0 ? item.featured_level : "-"
                    }</td>
                    <td>${item.sort_order}</td>
                    <td>
                        <label class="switch-sm">
                            <input type="checkbox" class="ptg-status-toggle" data-id="${
                              item.id
                            }" ${item.is_active == 1 ? "checked" : ""}>
                            ${statusBadge}
                        </label>
                    </td>
                    <td>
                        <button type="button" class="button button-small ptg-edit-btn" data-product='${JSON.stringify(
                          item
                        )}'>편집</button>
                    </td>
                </tr>
            `;
      $listBody.append(row);
    });
  }

  function openModal(mode, product) {
    $form[0].reset();
    $("#ptg-product-modal .ptg-modal-header h3").text(
      mode === "create" ? "상품 추가" : "상품 수정"
    );

    if (mode === "edit" && product) {
      $("#prod-id").val(product.id);
      $("#prod-code").val(product.product_code);
      $("#prod-price").val(product.price);
      $("#prod-title").val(product.title);
      $("#prod-desc").val(product.description);
      $("#prod-label").val(product.price_label);
      $("#prod-duration").val(product.duration_months);
      $("#prod-featured").val(product.featured_level);
      $("#prod-sort").val(product.sort_order);
      $("#prod-active").prop("checked", product.is_active == 1);

      if (product.features && Array.isArray(product.features)) {
        $("#prod-features").val(product.features.join("\n"));
      }
    } else {
      $("#prod-id").val("0");
      $("#prod-active").prop("checked", true);
    }

    $modal.fadeIn(200);
  }

  function closeModal() {
    $modal.fadeOut(200);
  }

  function saveProduct() {
    var formData = new FormData($form[0]);
    formData.append("action", "ptg_admin_save_product");
    formData.append("security", ptgAdmin.nonce);

    // Check required
    if (
      !formData.get("product_code") ||
      !formData.get("title") ||
      !formData.get("price")
    ) {
      alert("필수 입력 항목(상품코드, 상품명, 가격)을 확인해주세요.");
      return;
    }

    var $btn = $("#ptg-save-product-btn");
    $btn.prop("disabled", true).text("저장 중...");

    $.ajax({
      url: ptgAdmin.ajaxUrl,
      method: "POST",
      data: formData,
      processData: false, // important for FormData
      contentType: false, // important for FormData
      success: function (response) {
        $btn.prop("disabled", false).text("저장");
        if (response.success) {
          closeModal();
          loadProducts();
        } else {
          alert("저장 실패: " + response.data);
        }
      },
      error: function () {
        $btn.prop("disabled", false).text("저장");
        alert("서버 통신 오류");
      },
    });
  }

  function deleteProduct(id) {
    $.ajax({
      url: ptgAdmin.ajaxUrl,
      method: "POST",
      data: {
        action: "ptg_admin_delete_product",
        id: id,
        security: ptgAdmin.nonce,
      },
      success: function (response) {
        if (response.success) {
          loadProducts();
        } else {
          alert("삭제 실패: " + response.data);
        }
      },
      error: function () {
        alert("서버 통신 오류");
      },
    });
  }

  function toggleStatus(id, status) {
    $.ajax({
      url: ptgAdmin.ajaxUrl,
      method: "POST",
      data: {
        action: "ptg_admin_toggle_product_status",
        id: id,
        status: status,
        security: ptgAdmin.nonce,
      },
      success: function (response) {
        if (!response.success) {
          alert("상태 변경 실패: " + response.data);
          loadProducts(); // revert
        } else {
          loadProducts(); // refresh UI (badge update)
        }
      },
      error: function () {
        alert("서버 통신 오류");
        loadProducts(); // revert
      },
    });
  }
});
