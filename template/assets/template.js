(function ($) {
  function updateClock() {
    var now = new Date();
    $('#time').text(now.toLocaleTimeString('en-GB'));
  }

  function initOrderPage() {
    var $page = $('.order-page');
    if (!$page.length) return;

    var orderDiscount = 10;
    var prepayment = 5000;
    var selectedProductRow = null;
    var nextProductIndex = $page.find('tr[data-content]').length + 1;
    var demoProducts = [
      { name: 'Հունական աղցան', count: 1, price: 2400, sale: 0, time: '12:42:08', staff: 'Արամ Սարգսյան' },
      { name: 'Կապուչինո', count: 2, price: 1100, sale: 0, time: '12:44:33', staff: 'Նարե Մկրտչյան' },
      { name: 'Տիրամիսու', count: 1, price: 1800, sale: 5, time: '12:46:19', staff: 'Գոռ Մարտիրոսյան' }
    ];

    function toast(message) {
      $('.template-toast').text(message).fadeIn(160).delay(1300).fadeOut(220);
    }

    function numberValue(value) {
      var parsed = parseFloat(String(value || '').replace(/[^\d.-]/g, ''));
      return isNaN(parsed) ? 0 : parsed;
    }

    function formatMoney(value) {
      return String(Math.max(0, Math.round(value)));
    }

    function rowTotal($row) {
      var count = numberValue($row.find('.counter').text());
      var price = numberValue($row.attr('data-price'));
      var sale = numberValue($row.attr('data-sale'));
      var total = count * price * (1 - sale / 100);
      $row.find('.item-price').text(formatMoney(price));
      $row.find('.item-sale').text(formatMoney(sale));
      $row.find('.item-total').text(formatMoney(total));
      return total;
    }

    function updateTotals() {
      var subtotal = 0;
      $page.find('tr[data-content]').each(function () {
        subtotal += rowTotal($(this));
      });

      var afterDiscount = subtotal * (1 - orderDiscount / 100);
      var total = Math.max(0, afterDiscount - prepayment);
      var $lines = $page.find('#hour_price > li');
      $lines.eq(1).find('span').first().text(formatMoney(subtotal));
      $lines.eq(2).text('Զեղչ՝ ' + formatMoney(orderDiscount) + '%');
      $lines.eq(3).text('Կանխավճար՝ ' + formatMoney(prepayment));
      $page.find('#totalPrice').text(formatMoney(total));
      $('#orderDiscountInput').val(formatMoney(orderDiscount));
      $('#prepaymentInput').val(formatMoney(prepayment));
    }

    function setOrderStatus(status) {
      var labels = {
        blue: '2 / Դրսի սրահ',
        red: '2 / Դրսի սրահ',
        yellow: '2 / Դրսի սրահ'
      };
      var $status = $page.find('#status');
      $status.removeClass('blue red yellow').addClass(status);
      $status.find('.table_number').text(labels[status] || labels.blue);
      $page.find('.close_table, #check_idram, #check_evoca, #checkTelCell')
        .prop('disabled', status === 'blue');
    }

    function makeProductRow(item) {
      var id = nextProductIndex++;
      var total = item.count * item.price * (1 - item.sale / 100);
      return '<tr data-content="' + id + '" data-name="' + item.name + '" data-price="' + item.price + '" data-sale="' + item.sale + '">' +
        '<td><input type="checkbox" name="item_to_transfer" style="width:20px;height:16px"> ' + item.name + '</td>' +
        '<td><div class="tb_btns"><div>' +
        '<button class="btn btn-sm btn-warning menu_item_minus"><i class="glyphicon glyphicon-minus"></i></button>' +
        '<span class="counter">' + item.count + '</span>' +
        '<button class="btn btn-sm btn-success menu_item_plus"><i class="glyphicon glyphicon-plus"></i></button>' +
        '</div><button class="btn btn-sm btn-success pull-right menu_item_edit_submit toDisable" disabled><i class="glyphicon glyphicon-ok"></i></button></div></td>' +
        '<td class="hidden-sm item-price">' + item.price + '</td>' +
        '<td class="hidden-sm item-sale">' + item.sale + '</td>' +
        '<td class="item-total">' + formatMoney(total) + '</td>' +
        '<td>' + item.time + '</td>' +
        '<td>' + item.staff + '</td>' +
        '<td><div>' +
        '<button class="btn btn-xs btn-success to_sale_product inTableIconButton" title="Զեղչ" data-toggle="modal" data-target="#saleProductModal"><span style="font-weight:bolder !important">%</span></button>' +
        '<button class="btn btn-xs btn-danger menu_item_delete inTableIconButton"><img src="assets/img/icons/trash.svg" alt=""></button>' +
        '<button class="btn btn-xs btn-success set_staff inTableIconButton" title="Վաճառող" data-toggle="modal" data-target="#setStaffModal"><i class="icon-user"></i></button>' +
        '</div></td></tr>';
    }

    $page.on('click', '.menu_item_plus, .menu_item_minus', function (event) {
      event.preventDefault();
      var $row = $(this).closest('tr[data-content]');
      var $counter = $row.find('.counter');
      var count = numberValue($counter.text());
      count += $(this).hasClass('menu_item_plus') ? 1 : -1;
      count = Math.max(1, count);
      $counter.text(count);
      $row.find('.menu_item_edit_submit, .menu_item_minus').prop('disabled', false);
      updateTotals();
    });

    $page.on('click', '.menu_item_edit_submit', function (event) {
      event.preventDefault();
      $(this).prop('disabled', true);
      toast('Քանակը պահպանվեց');
    });

    $page.on('click', '.menu_item_delete', function (event) {
      event.preventDefault();
      var $row = $(this).closest('tr[data-content]');
      $row.nextUntil('tr[data-content]').filter('.order-extra-row').remove();
      $row.remove();
      updateTotals();
      toast('Ապրանքը ջնջվեց');
    });

    $page.on('click', '.to_sale_product', function () {
      selectedProductRow = $(this).closest('tr[data-content]');
      $('#productDiscountInput').val(selectedProductRow.attr('data-sale') || 0);
    });

    $('#applyProductDiscount').on('click', function () {
      if (!selectedProductRow) return;
      var sale = Math.min(100, Math.max(0, numberValue($('#productDiscountInput').val())));
      selectedProductRow.attr('data-sale', sale);
      updateTotals();
      $('#saleProductModal').modal('hide');
      toast('Ապրանքի զեղչը կիրառվեց');
    });

    $('#applyOrderDiscount').on('click', function () {
      orderDiscount = Math.min(100, Math.max(0, numberValue($('#orderDiscountInput').val())));
      updateTotals();
      $('#discountModal').modal('hide');
      toast('Զեղչը կիրառվեց');
    });

    $('#applyPrepayment').on('click', function () {
      prepayment = Math.max(0, numberValue($('#prepaymentInput').val()));
      updateTotals();
      $('#prepaymentModal').modal('hide');
      toast('Կանխավճարը պահպանվեց');
    });

    $('#applyClientName').on('click', function () {
      var name = $('#clientNameInput').val().trim() || 'Սոնա';
      $page.find('#hour_price').closest('section').children('p').last().text('Հաճախորդ՝ ' + name);
      $('#clientModal').modal('hide');
      toast('Հաճախորդը ընտրվեց');
    });

    $page.on('click', '.set_staff', function () {
      selectedProductRow = $(this).closest('tr[data-content]');
      $('#setStaffSelect').val(selectedProductRow.children('td').eq(6).text().trim());
    });

    $('#applySetStaff').on('click', function () {
      if (!selectedProductRow) return;
      selectedProductRow.children('td').eq(6).text($('#setStaffSelect').val());
      $('#setStaffModal').modal('hide');
      toast('Վաճառողը փոխվեց');
    });

    function readPendingProducts() {
      var raw = window.localStorage.getItem('roomsOrderPendingProducts');
      if (!raw) return [];
      window.localStorage.removeItem('roomsOrderPendingProducts');
      try {
        return JSON.parse(raw) || [];
      } catch (error) {
        return [];
      }
    }

    $page.find('#addOrderBtn').on('click', function () {
      window.location.href = 'rooms-add-order-item.html';
    });

    $page.find('#addSubtable').on('click', function (event) {
      event.preventDefault();
      var number = $page.find('.subtable').length + 1;
      var html = '<table class="table table-hover subtable" data-subtable="' + number + '">' +
        '<thead><tr><th>Անվանում</th><th>Քանակ</th><th class="hidden-sm">Արժեք</th><th class="hidden-sm">Զեղչ</th><th>Ընդհ․</th><th>Ժամ</th><th>Վաճառող</th><th><img src="assets/img/icons/gear.svg" alt=""></th></tr></thead><tbody></tbody></table>';
      $page.find('#subtablesContainer').append(html);
      toast('Ենթասեղանը ավելացվեց');
    });

    $page.find('#replaceItems').on('click', function (event) {
      event.preventDefault();
      var $checkedRows = $page.find('input[name="item_to_transfer"]:checked').closest('tr[data-content]');
      if (!$checkedRows.length) {
        toast('Ապրանք նշված չէ');
        return;
      }
      var $targetTable = $page.find('.subtable').last();
      if ($targetTable.is($page.find('.subtable').first())) {
        $page.find('#addSubtable').trigger('click');
        $targetTable = $page.find('.subtable').last();
      }
      $checkedRows.each(function () {
        var $row = $(this);
        var $extras = $row.nextUntil('tr[data-content]').filter('.order-extra-row');
        $targetTable.find('tbody').append($row).append($extras);
        $row.find('input[name="item_to_transfer"]').prop('checked', false);
      });
      toast('Ապրանքները տեղափոխվեցին');
    });

    $page.find('#transformTable').on('click', function () {
      toast('Սեղանի տեղափոխման ռեժիմը միացավ');
      window.location.hash = 'transform-table';
    });

    $page.find('.print_check').on('click', function () {
      setOrderStatus('red');
      toast('Հաշիվը տպվեց, սեղանը պատրաստ է փակման');
    });

    $('#confirmCloseOrder').on('click', function () {
      setOrderStatus('yellow');
      $('#closeOrderModal').modal('hide');
      toast('Սեղանը փակվեց');
    });

    $('#confirmZeroCloseOrder').on('click', function () {
      prepayment = 0;
      orderDiscount = 0;
      $page.find('tr[data-content]').remove();
      updateTotals();
      setOrderStatus('yellow');
      $('#zeroCloseModal').modal('hide');
      toast('Պատվերը զրոյացվեց');
    });

    $page.find('#check_idram, #check_evoca, #checkTelCell').on('click', function () {
      toast('Վճարումը հաստատված է');
      setOrderStatus('red');
    });

    $page.find('#commandBtn').on('click', function () {
      toast('Հաճախորդների քանակը պահպանվեց՝ ' + ($page.find('#clients_count').val() || 0));
    });

    $page.find('#clients_count').on('keyup', function (event) {
      if (event.keyCode === 13) $page.find('#commandBtn').trigger('click');
    });

    $page.find('#checkComment').on('change', function () {
      toast('Կտրոնի նկարագրությունը պահպանվեց');
    });

    $page.find('.table-type-select').on('change', function () {
      toast('Պատվերի տեսակը փոխվեց՝ ' + $(this).val());
    });

    $page.find('#instant_sale').on('keyup', function (event) {
      if (event.keyCode !== 13) return;
      orderDiscount = 10;
      updateTotals();
      toast('Զեղչի քարտը կիրառվեց');
      $(this).val('');
    });

    $page.find('#manr_mutq').on('input', function () {
      var paid = numberValue($(this).val());
      var total = numberValue($page.find('#totalPrice').text());
      $page.find('#manr_elq').val(formatMoney(paid - total));
    });

    setOrderStatus('blue');
    readPendingProducts().forEach(function (product) {
      $page.find('#subtablesContainer .subtable').first().find('tbody').append(makeProductRow(product));
    });
    updateTotals();
  }

  function initAddOrderItemPage() {
    var $page = $('.add-order-page');
    if (!$page.length) return;

    var selectedGroup = '';
    var selectedProduct = null;

    function toast(message) {
      $('.template-toast').text(message).fadeIn(160).delay(1300).fadeOut(220);
    }

    function numberValue(value) {
      var parsed = parseFloat(String(value || '').replace(/[^\d.-]/g, ''));
      return isNaN(parsed) ? 0 : parsed;
    }

    function tempRows() {
      return $page.find('#prod_table tbody tr');
    }

    function updateAddOrderTotal() {
      var total = 0;
      tempRows().each(function () {
        var $row = $(this);
        total += numberValue($row.attr('data-price')) * numberValue($row.find('.counter-display').text());
      });
      $page.find('.add-order-total').text(Math.round(total));
      $page.find('.accept').prop('disabled', tempRows().length === 0);
    }

    function makeTempRow(product) {
      return '<tr class="cursor-hand" data-name="' + product.name + '" data-price="' + product.price + '">' +
        '<td>' + product.name + '</td>' +
        '<td class="editable menu_item_count"><span class="counter-display">' + product.count + '</span></td>' +
        '<td class="hidden-phone item-price">' + product.price + '</td>' +
        '<td class="menu_item_total_price">' + Math.round(product.price * product.count) + '</td>' +
        '<td class="action"><div class="inTableButtonsContainer">' +
        '<button class="save_tr btn btn-success btn-sm inTableIconButton"><i class="icon-ok"></i></button>' +
        '<button class="cancel_tr btn btn-primary btn-sm inTableIconButton"><i class="icon-remove"></i></button>' +
        '<button class="delete_tr btn btn-danger btn-xs inTableIconButton"><img src="assets/img/icons/trash.svg" alt=""></button>' +
        '</div></td></tr>';
    }

    function addProduct($product) {
      var product = {
        name: $product.data('name'),
        price: numberValue($product.data('price')),
        count: Math.max(1, numberValue($page.find('#count').val()) || 1)
      };
      var $existing = tempRows().filter(function () {
        return $(this).attr('data-name') === product.name;
      });
      if ($existing.length) {
        var $counter = $existing.find('.counter-display');
        $counter.text(numberValue($counter.text()) + product.count);
        $existing.find('.menu_item_total_price').text(Math.round(product.price * numberValue($counter.text())));
      } else {
        $page.find('#prod_table tbody').append(makeTempRow(product));
      }
      updateAddOrderTotal();
      toast('Ապրանքը ավելացվեց');
    }

    function applyFilter() {
      var query = ($page.find('#menu_search').val() || '').toLowerCase();
      $page.find('.product_item_pos').each(function () {
        var $item = $(this);
        var matchesGroup = !selectedGroup || $item.data('group') === selectedGroup;
        var matchesSearch = !query || String($item.data('name')).indexOf(query) !== -1;
        $item.toggle(matchesGroup && matchesSearch);
      });
    }

    $page.on('click', '.parent_menu', function (event) {
      event.preventDefault();
      var $item = $(this);
      selectedGroup = $item.data('group');
      $page.find('.parent_menu').removeClass('menu_item_active');
      $page.find('#day_menu').removeClass('menu_item_active');
      $item.addClass('menu_item_active');
      $page.find('.menuList').hide();
      $page.find('.parent_' + $item.attr('menu_id')).show();
      $page.find('#menu_search').val('');
      applyFilter();
    });

    $page.find('#day_menu').on('click', function () {
      selectedGroup = '';
      $page.find('.parent_menu').removeClass('menu_item_active');
      $page.find('.sub_menu').removeClass('sub_menu_active');
      $page.find('.menuList').hide();
      $(this).addClass('menu_item_active');
      $page.find('#menu_search').val('');
      applyFilter();
    });

    $page.on('click', '.sub_menu', function () {
      selectedGroup = $(this).data('group') || selectedGroup;
      $page.find('.sub_menu').removeClass('sub_menu_active');
      $(this).addClass('sub_menu_active');
      $page.find('#menu_search').val($(this).data('filter'));
      applyFilter();
    });

    $page.find('#search_mi').on('click', applyFilter);
    $page.find('#menu_search').on('input', applyFilter);

    $page.on('click', '.product_item', function () {
      selectedProduct = $(this);
      $page.find('.selected_item').removeClass('selected_item');
      selectedProduct.addClass('selected_item');
      $page.find('.getName').text(selectedProduct.data('name'));
      if (!$page.find('#count').val()) $page.find('#count').val('1');
      toast('Ընտրվեց՝ ' + selectedProduct.data('name'));
    });

    $page.on('click', '.counter-plus, .counter-minus', function (event) {
      event.preventDefault();
      var $counter = $(this).siblings('.counter-display');
      var count = numberValue($counter.text());
      count += $(this).hasClass('counter-plus') ? 1 : -1;
      $counter.text(Math.max(1, count));
      updateAddOrderTotal();
    });

    $page.on('click', '.menu_item_count', function () {
      var $counter = $(this).find('.counter-display');
      $counter.text(Math.max(1, numberValue($counter.text()) + 1));
      $(this).closest('tr').find('.menu_item_total_price')
        .text(Math.round(numberValue($(this).closest('tr').attr('data-price')) * numberValue($counter.text())));
      updateAddOrderTotal();
    });

    $page.on('click', '.delete_tr', function (event) {
      event.preventDefault();
      $(this).closest('tr').remove();
      updateAddOrderTotal();
    });

    $page.on('click', '.number_div', function () {
      var $input = $page.find('#count');
      var value = $input.val() === '0' ? '' : $input.val();
      $input.val(value + $(this).text());
    });

    $page.on('click', '.del_div', function () {
      var $input = $page.find('#count');
      var value = $input.val();
      $input.val(value.length > 1 ? value.slice(0, -1) : '');
    });

    $page.find('.add_order_item').on('click', function () {
      if (!selectedProduct) {
        toast('Ընտրեք ապրանքը');
        return;
      }
      addProduct(selectedProduct);
      selectedProduct.removeClass('selected_item');
      selectedProduct = null;
      $page.find('#count').val('');
    });

    $page.find('.accept').on('click', function () {
      var products = tempRows().map(function () {
        var $row = $(this);
        return {
          name: $row.attr('data-name'),
          price: numberValue($row.attr('data-price')),
          count: numberValue($row.find('.counter-display').text()),
          sale: 0,
          time: new Date().toLocaleTimeString('en-GB'),
          staff: 'Արամ Սարգսյան'
        };
      }).get();
      window.localStorage.setItem('roomsOrderPendingProducts', JSON.stringify(products));
      window.location.href = 'rooms-table-order.html';
    });

    $page.find('.add-order-back').on('click', function () {
      window.location.href = 'rooms-table-order.html';
    });

    $page.find('.parent_menu').first().trigger('click');
    updateAddOrderTotal();
  }

  function initStoreDocumentDetailPage() {
    var $page = $('.store-document-detail-page');
    if (!$page.length) return;

    function numberValue(value) {
      var parsed = parseFloat(String(value || '').replace(/[^\d.-]/g, ''));
      return isNaN(parsed) ? 0 : parsed;
    }

    function format(value) {
      return Math.round(value * 100) / 100;
    }

    function cell(value) {
      return '<td>' + (value == null || value === '' ? '' : value) + '</td>';
    }

    function actionCell(mode) {
      if (mode === 'submitted') return '<td></td>';
      return '<td><div class="inTableButtonsContainer">' +
        '<button class="btn btn-warning btn-xs inTableIconButton" data-toggle="modal" data-target="#editDocContModal"><img src="assets/img/icons/pencil.svg" alt=""></button>' +
        '<button class="btn btn-danger btn-xs inTableIconButton" data-toggle="modal" data-target="#deleteDocContModal"><img src="assets/img/icons/trash.svg" alt=""></button>' +
        '</div></td>';
    }

    function rowsForTemplate(doc) {
      var amount = Math.abs(numberValue(doc.amount)) || 0;
      var template = doc.template;

      if (template === 'transfer') {
        return {
          title: 'Տրանսֆերի կազմ',
          head: ['Անվանում', 'Ելքային պահեստ', 'Մուտքային պահեստ', 'Քանակ', 'Ընդհանուր ինքնարժեք', 'Նկարագրություն'],
          rows: [
            ['Գարեջուր 0.5լ', 'Արտադրամաս', 'Բար', '24.000', amount || 16800, doc.description],
            ['Սառույց կգ', 'Գլխավոր պահեստ', 'Բար', '6.000', amount ? format(amount / 2) : 1200, doc.description]
          ]
        };
      }

      if (template === 'semiFinished') {
        return {
          title: 'Կիսապատրաստուկի բաղադրություն',
          head: ['Տիպ', 'Պահեստ', 'Հումք', 'Քանակ', 'Ինքնարժեք', 'Նկարագրություն'],
          rows: [
            ['Կիսապատրաստուկ', 'Խոհանոց', 'Տավարի միս կգ', '3.000', amount || 0, doc.description],
            ['Կիսապատրաստուկ', 'Խոհանոց', 'Համեմունք կգ', '0.450', amount || 0, doc.description]
          ]
        };
      }

      if (template === 'recalculation' || template === 'backDatedRecalculation' || template === 'hard_recalculation') {
        return {
          title: template === 'backDatedRecalculation' ? 'Հետին ամսաթվով վերահաշվարկ' : 'Վերահաշվարկ',
          head: ['Անվանում', 'Պահեստ', 'Ծրագրային քանակ', 'Փաստացի քանակ', 'Տարբերություն', 'Գումար'],
          rows: [
            ['Լոլիկ կգ', 'Բար', '48.000', '43.430', '-4.570', amount || 2969.18],
            ['Գարեջուր 0.5լ', 'Բար', '120.000', '116.000', '-4.000', amount ? format(amount / 3) : 0]
          ]
        };
      }

      if (template === 'buy' || template === 'entry') {
        return {
          title: template === 'buy' ? 'Մատակարարից մուտք' : 'Մուտքի փաստաթուղթ',
          head: ['Անվանում', 'Մուտքային պահեստ', 'Քանակ', 'Միավորի արժեք', 'Գումար', 'Նկարագրություն'],
          rows: [
            ['Լոլիկ կգ', doc.company || 'Գլխավոր պահեստ', '28.000', '650', '18200', doc.description],
            ['Վարունգ կգ', doc.company || 'Գլխավոր պահեստ', '12.000', '520', '6240', doc.description]
          ]
        };
      }

      return {
        title: 'Փաստաթղթի կազմ',
        head: ['Անվանում', 'Պահեստ', 'Քանակ', 'Ընդհանուր ինքնարժեք', 'Նկարագրություն'],
        rows: [
          ['Սուրճ կգ', doc.company || 'Գլխավոր պահեստ', '2.000', amount || 8400, doc.description],
          ['Կաթ լ', doc.company || 'Գլխավոր պահեստ', '8.000', amount ? format(amount / 2) : 3200, doc.description]
        ]
      };
    }

    function renderTable(doc, mode) {
      var model = rowsForTemplate(doc);
      var head = model.head.concat(['<i class="icon-cogs"></i>']);
      $page.find('[data-doc-table-title]').text(model.title);
      $page.find('[data-doc-table-head]').html('<tr>' + head.map(function (title) {
        return '<th>' + title + '</th>';
      }).join('') + '</tr>');
      $page.find('[data-doc-table-body]').html(model.rows.map(function (row) {
        return '<tr>' + row.map(cell).join('') + actionCell(mode) + '</tr>';
      }).join(''));
      $page.find('[data-doc-table-foot]').html('<tr>' + head.map(function () {
        return '<th></th>';
      }).join('') + '</tr>');
    }

    var data = [];
    try {
      data = JSON.parse($('#storeDocumentDetailData').text() || '[]');
    } catch (error) {
      data = [];
    }

    var params = new URLSearchParams(window.location.search);
    var id = params.get('id') || $page.data('default-id');
    var template = params.get('template');
    var doc = data.find(function (item) {
      return String(item.id) === String(id);
    }) || data.find(function (item) {
      return item.template === template;
    }) || data[0];
    if (!doc) return;
    if (template) doc.template = template;

    var mode = $page.data('detail-mode') || 'content';
    $page.find('[data-doc-field]').each(function () {
      var field = $(this).data('doc-field');
      $(this).text(doc[field] || (field === 'company' || field === 'identification' ? '-' : ''));
    });
    $page.find('[data-edit-only]').toggle(mode !== 'submitted');
    renderTable(doc, mode);
  }

  function initStoreBalancePage() {
    var $page = $('.store-balance-page');
    if (!$page.length) return;

    var params = new URLSearchParams(window.location.search);
    var activeStore = params.get('store') || '2';
    var activeFilter = 'all';

    function numberValue(value) {
      var parsed = parseFloat(String(value || '').replace(/[^\d.-]/g, ''));
      return isNaN(parsed) ? 0 : parsed;
    }

    function updateTotals() {
      var quantity = 0;
      var real = 0;
      $page.find('.store-balance-row:visible').each(function () {
        var $row = $(this);
        quantity += numberValue($row.find('.store-balance-quantity').text());
        real += numberValue($row.find('.store-balance-real').text());
      });
      $page.find('.store-balance-total-quantity').text(Math.round(quantity * 1000) / 1000);
      $page.find('.store-balance-total-real').text(Math.round(real * 100) / 100);
    }

    function applyStoreBalanceFilters() {
      var category = $page.find('#materialCategoryFilter').val();
      var search = String($page.find('#storeBalanceSearch').val() || '').toLowerCase();

      $page.find('.store-balance-row').each(function () {
        var $row = $(this);
        var matchesStore = String($row.data('store')) === String(activeStore);
        var matchesCategory = category === '0' || $row.data('category') === category;
        var matchesFilter = activeFilter === 'all' ||
          (activeFilter === 'filterEndingMaterial' && ($row.data('filter-type') === 'ending' || $row.data('filter-type') === 'zero')) ||
          (activeFilter === 'filterExcessMaterial' && $row.data('filter-type') === 'excess');
        var matchesSearch = !search || $row.text().toLowerCase().indexOf(search) !== -1;
        $row.toggle(matchesStore && matchesCategory && matchesFilter && matchesSearch);
      });

      updateTotals();
    }

    $page.find('[data-store-tab]').removeClass('active')
      .filter('[data-store-tab="' + activeStore + '"]').addClass('active');
    if (!$page.find('[data-store-tab].active').length) {
      activeStore = String($page.find('[data-store-tab]').first().data('store-tab'));
      $page.find('[data-store-tab]').first().addClass('active');
    }
    $page.find('#store_id').val(activeStore);

    $page.find('.store-balance-tabs a').on('click', function (event) {
      event.preventDefault();
      var $tab = $(this).closest('[data-store-tab]');
      activeStore = String($tab.data('store-tab'));
      $page.find('[data-store-tab]').removeClass('active');
      $tab.addClass('active');
      $page.find('#store_id').val(activeStore);
      applyStoreBalanceFilters();
    });

    $page.find('#materialCategoryFilter, #storeBalanceSearch').on('change input', applyStoreBalanceFilters);

    $page.find('.filterMaterial').on('click', function () {
      activeFilter = $(this).val();
      $page.find('.filterMaterial').removeClass('active');
      $(this).addClass('active');
      applyStoreBalanceFilters();
    });

    applyStoreBalanceFilters();
  }

  function initStaffPage() {
    if ($('body').data('page') !== 'staff.html') return;

    function initStaffSelect2($scope) {
      if (!$.fn.select2) return;
      $scope.find('select[name="attached_menu"]').each(function () {
        var $select = $(this);
        if ($select.data('select2')) return;
        $select.select2({ width: '100%' });
      });
    }

    function setStaffStep($modal, stepIndex) {
      var $steps = $modal.find('.staff-fieldset');
      var $tabs = $modal.find('.stepy-titles li');
      var lastStepIndex = $steps.length - 1;
      var nextStep = Math.max(0, Math.min(stepIndex, lastStepIndex));

      $steps.hide().eq(nextStep).show();
      $tabs.removeClass('current-step').eq(nextStep).addClass('current-step');

      $modal.find('.button-back').toggle(nextStep > 0);
      $modal.find('.button-next').toggle(nextStep < lastStepIndex);
      $modal.find('.finish').toggle(nextStep === lastStepIndex);

      if (nextStep === 1) initStaffSelect2($modal);
    }

    $('.staff-modal').each(function () {
      initStaffSelect2($(this));
      setStaffStep($(this), 0);
    });

    $('.staff-modal').on('show.bs.modal', function () {
      initStaffSelect2($(this));
      setStaffStep($(this), 0);
    });

    $('.staff-modal').on('click', '.stepy-titles li', function () {
      var $modal = $(this).closest('.staff-modal');
      setStaffStep($modal, $(this).index());
    });

    $('.staff-modal').on('click', '.button-next', function () {
      var $modal = $(this).closest('.staff-modal');
      var currentStep = $modal.find('.stepy-titles li.current-step').index();
      setStaffStep($modal, currentStep + 1);
    });

    $('.staff-modal').on('click', '.button-back', function () {
      var $modal = $(this).closest('.staff-modal');
      var currentStep = $modal.find('.stepy-titles li.current-step').index();
      setStaffStep($modal, currentStep - 1);
    });
  }

  $(function () {
    var current = $('body').data('page');
    if (current) {
      var $currentLinks = $('[data-page="' + current + '"]');
      $currentLinks.each(function () {
        var $link = $(this);
        if ($link.closest('ul.sub').length) {
          $link.addClass('activated');
          $link.closest('li.sub-menu').addClass('active').children('a').addClass('active');
        } else {
          $link.addClass('active').closest('#nav-accordion > li').addClass('active');
        }
      });
    }
    $('#nav-accordion > li.sub-menu').not('.active').children('ul.sub').hide();
    $('#nav-accordion > li.sub-menu.active').children('ul.sub').show();

    function toggleSubmenu($item) {
      var $link = $item.children('a');
      var isOpen = $item.children('ul.sub').is(':visible');

      $('#nav-accordion > li.sub-menu').not($item).removeClass('active')
        .children('a').removeClass('active').end()
        .children('ul.sub').slideUp(180);

      if (isOpen) {
        $item.removeClass('active');
        $link.removeClass('active');
        $item.children('ul.sub').slideUp(180);
      } else {
        $item.addClass('active');
        $link.addClass('active');
        $item.children('ul.sub').slideDown(180);
      }
    }

    $('#nav-accordion > li.sub-menu > a .dcjq-icon').on('click', function (event) {
      event.preventDefault();
      event.stopPropagation();
      toggleSubmenu($(this).closest('li.sub-menu'));
    });

    $('.sidebar-toggle-box').on('click', function () {
      $('body').toggleClass('sidebar-open');
    });

    $('[data-toast]').on('click', function () {
      var text = $(this).data('toast');
      $('.template-toast').text(text).fadeIn(160).delay(1800).fadeOut(220);
    });

    $('.get-edit').on('click', function () {
      $('#editModal').modal('show');
    });

    $('.table_panel').on('click', function () {
      var $panel = $(this).closest('section.panel');
      $panel.css({ transform: 'scale(0.9)', boxShadow: '0 0 5px #f7f7f7' });
      setTimeout(function () {
        $panel.css({ transform: '', boxShadow: '' });
        window.location.href = 'rooms-table-order.html';
      }, 500);
    });

    $('.close_table').on('click', function () {
      $('#closeOrderModal').modal('show');
    });

    function syncHallCarets() {
      $('.rooms-tables-page .panel-collapse').each(function () {
        var $collapse = $(this);
        $collapse.closest('.panel').find('.panel-heading')
          .toggleClass('is-collapsed', !$collapse.hasClass('in'));
      });
    }

    function scrollToHall($target) {
      setTimeout(function () {
        var top = $target.closest('.panel').offset().top - 70;
        $('html, body').stop(true).animate({ scrollTop: top }, 180);
      }, 160);
    }

    function openOnlyHall($target, shouldScroll) {
      if (!$target.length) return;

      $('.rooms-tables-page .panel-collapse').not($target)
        .removeClass('in')
        .stop(true, true)
        .slideUp(160, syncHallCarets);

      $target
        .addClass('in')
        .stop(true, true)
        .slideDown(160, syncHallCarets);

      if (shouldScroll) scrollToHall($target);
    }

    $('.rooms-tables-page .rooms-hall-buttons [data-hall-target]').on('click', function (event) {
      event.preventDefault();
      openOnlyHall($($(this).attr('data-hall-target')), true);
    });

    $('.rooms-tables-page [data-hall-toggle]').on('click', function (event) {
      event.preventDefault();
      var $target = $($(this).attr('data-hall-toggle'));
      if (!$target.length) return;

      if ($target.hasClass('in')) {
        $target.closest('.panel').find('.panel-heading').addClass('is-collapsed');
        $target
          .removeClass('in')
          .stop(true, true)
          .slideUp(160, syncHallCarets);
      } else {
        $target.closest('.panel').find('.panel-heading').removeClass('is-collapsed');
        $target
          .addClass('in')
          .stop(true, true)
          .slideDown(160, syncHallCarets);
      }
    });

    syncHallCarets();
    initOrderPage();
    initAddOrderItemPage();
    initStoreDocumentDetailPage();
    initStoreBalancePage();
    initStaffPage();

    updateClock();
    setInterval(updateClock, 1000);
  });
})(jQuery);
