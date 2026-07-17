const fs = require('fs');
const path = require('path');

const outDir = __dirname;

const nav = [
  { title: 'AI support', icon: 'fa fa-magic', page: 'ai-generator.html' },
  { title: 'Տեղեկատու', icon: 'fa fa-folder', page: 'index.html' },
  { title: 'Արագ սնունդ', icon: 'fa fa-cutlery', page: 'fast-food.html', children: [
    { title: 'Պատմություն', page: 'fast-food-history.html' },
    { title: 'Պատրաստի պատվերներ', page: 'fast-food-handler.html' },
  ] },
  { title: 'Հաճախորդի պատվեր', icon: 'fa fa-bell', page: 'incoming-orders.html' },
  { title: 'Սրահներ / Սեղաններ', icon: 'fa fa-cutlery', page: 'rooms-tables.html', children: [
    { title: 'Կարգավորումներ', page: 'rooms-hall.html' },
    { title: 'Կտրոններ', page: 'rooms-printer-error.html' },
    { title: 'Հդմ Կտրոններ', page: 'rooms-fiscal-error.html' },
    { title: 'Ընթացիկ հաշիվներ', page: 'rooms-invisible-orders.html' },
    { title: 'Սեղանի ամրագրում', page: 'reserve.html' },
    { title: 'Խոհարարի էկրան', page: 'rooms-kitchen.html' },
  ] },
  { title: 'cash', icon: 'fa fa-money', page: 'cash.html', children: [
    { title: 'Դրամարկղ', page: 'cash.html' },
    { title: 'Ընդհանրացված', page: 'cash-totalized.html' },
    { title: 'Դրամարկղի կարգավորումներ', page: 'cash-settings.html' },
    { title: 'Ծախսերի տեսակներ', page: 'expense-types.html' },
  ] },
  { title: 'reports', icon: 'fa fa-file-text-o', page: 'reports.html', children: [
    { title: 'Ընդհանուր', page: 'reports.html' },
    { title: 'Սեղանների պատմություն', page: 'reports-tables-history.html' },
    { title: 'Առաքումների պատմություն', page: 'reports-delivery.html' },
    { title: 'Տեղափոխված սեղաններ', page: 'reports-moved-tables.html' },
    { title: 'Տեղափոխված ապրանքներ', page: 'reports-moved-items.html' },
    { title: 'Առևտուր', page: 'reports-food.html' },
    { title: 'Փաթեթներ', page: 'reports-combo.html' },
    { title: 'Հումքեր', page: 'reports-ingredients.html' },
    { title: 'Խմբի պատմություն', page: 'reports-material-group-history.html' },
    { title: 'Գործ. պատմություն', page: 'reports-logs.html' },
  ] },
  { title: 'analysis', icon: 'fa fa-bar-chart', page: 'analysis-planning.html', children: [
    { title: 'Պլանավորում', page: 'analysis-planning.html' },
    { title: 'Թոփ/պասիվ վաճառք', page: 'analysis-top-passive.html' },
    { title: 'Պատվերների ստատիստիկա', page: 'analysis-order-statistics.html' },
    { title: 'Վաճառքի ստատիստիկա', page: 'analysis-sales-statistics.html' },
  ] },
  { title: 'store', icon: 'fa fa-archive', page: 'store.html', children: [
    { title: 'Պահեստներ', page: 'store.html' },
    { title: 'Խմբեր', page: 'store-material-category.html' },
    { title: 'Հումքեր', page: 'store-items.html' },
    { title: 'Փաստաթղթեր', page: 'store-documents.html' },
    { title: 'Մնացորդներ', page: 'store-balance.html' },
    { title: 'Հումքի շարժ', page: 'store-timeline.html' },
    { title: 'Պատմություն', page: 'store-history.html' },
    { title: 'Մնացորդների հաշվ.', page: 'store-sum.html' },
  ] },
  { title: 'menu', icon: 'fa fa-book', page: 'menu.html', children: [
    { title: 'Մենյուի բաժիններ', page: 'menu.html' },
    { title: 'Օրվա մենյու', page: 'menu-day.html' },
    { title: 'Կասեցնել', page: 'menu-suspend.html' },
    { title: 'Սառեցված ապրանքներ', page: 'menu-frozen.html' },
  ] },
  { title: 'Ընկերություններ', icon: 'fa fa-briefcase', page: 'company.html', children: [
    { title: 'Մատակարարներ', page: 'company.html' },
    { title: 'Պարտքեր', page: 'company-expenses.html' },
  ] },
  { title: 'Հաճախորդներ', icon: 'fa fa-users', page: 'clients.html', children: [
    { title: 'Հաճախորդներ', page: 'clients.html' },
    { title: 'Պարտքեր', page: 'clients-expenses.html' },
    { title: 'Քարտեր', page: 'clients-cards-page.html' },
    { title: 'Քարտերի պատմություն', page: 'clients-cards.html' },
    { title: 'Արձագանքների տեսակներ', page: 'clients-response-settings.html' },
    { title: 'Հաճախորդների արձագանքներ', page: 'clients-response.html' },
    { title: 'Չսպասարկված հաճախորդներ', page: 'clients-unserved.html' },
    { title: 'Հաճախորդների բողոքներ', page: 'clients-complaints.html' },
  ] },
  { title: 'staff', icon: 'fa fa-male', page: 'staff.html', children: [
    { title: 'Անձնակազմ', page: 'staff.html' },
    { title: 'Աշխ. պարտքեր', page: 'staff-expenses.html' },
    { title: 'Ներկայություն', page: 'staff-present.html' },
    { title: 'Աշխատավարձի տիպ', page: 'staff-salary-types.html' },
    { title: 'Աշխատավարձի սահմանում', page: 'staff-salary-values.html' },
  ] },
  { title: 'Աշխատավարձի սահմանում', icon: 'fa fa-check-square-o', page: 'present.html' },
  { title: 'Կարգավորումներ', icon: 'fa fa-cog', page: 'settings.html', children: [
    { title: 'Ընդհանուր', page: 'settings.html' },
    { title: 'Օգտատերեր', page: 'settings-users.html' },
    { title: 'Պատրաստման վայրեր', page: 'settings-checks-place.html' },
    { title: 'SMS Շաբլոններ', page: 'settings-sms.html' },
    { title: 'ՀԴՄ լիցենզիա', page: 'settings-hdm-license.html' },
    { title: 'ՀԴՄ կարգավորումներ', page: 'settings-hdm.html' },
    { title: 'ՀԴՄ կարգավորումներ(նոր)', page: 'settings-fiscal.html' },
    { title: 'Սննդատեսակներ', page: 'settings-meal-types.html' },
    { title: 'Մասնաճյուղեր', page: 'settings-branch.html' },
    { title: 'Կցել մասնաճյուղեր', page: 'settings-user-branch.html' },
    { title: 'Բազայի արխիվացում', page: 'settings-archive-db.html' },
  ] },
  { title: 'Ադմինիստրատիվ', icon: 'fa fa-cog', page: 'admin-settings.html', children: [
    { title: 'Ընդհանուր', page: 'admin-settings.html' },
    { title: 'Սուպեր ադմին', page: 'admin-super.html' },
    { title: 'Սարքի կարգավորումներ', page: 'admin-device.html' },
    { title: 'Տպիչի մոնիտորինգ', page: 'admin-printer-monitoring.html' },
    { title: 'Բոնուս քարտի մասնաճյուղեր', page: 'admin-profiles-bonus.html' },
    { title: 'Հերթի աշխատանքի պատմություն', page: 'admin-queue-jobs.html' },
    { title: 'Հուշումների էջ', page: 'admin-hints.html' },
  ] },
  { title: 'Հաճախորդի էկրան', icon: 'fa fa-desktop', page: 'client-screen.html' },
  { title: 'Hidden / Popups', icon: 'fa fa-eye-slash', page: 'hidden-popups.html' },
];

function flatten(items) {
  const result = [];
  for (const item of items) {
    result.push(item);
    if (item.children) result.push(...item.children.map(child => ({ ...child, parent: item.title })));
  }
  return result;
}

const pages = flatten(nav);
const standalonePages = [
  { title: 'Պատվերի էջ', page: 'rooms-table-order.html', parent: 'Սրահներ / Սեղաններ', parentPage: 'rooms-tables.html' },
  { title: 'Ավելացնել ապրանք', page: 'rooms-add-order-item.html', parent: 'Սրահներ / Սեղաններ', parentPage: 'rooms-tables.html' },
  { title: 'Հարկի հատակագիծ', page: 'rooms-hall-planning.html', parent: 'Սրահներ / Սեղաններ', parentPage: 'rooms-tables.html' },
  { title: 'Սեղաններ Կարգավորումներ', page: 'rooms-hall-tables.html', parent: 'Սրահներ / Սեղաններ', parentPage: 'rooms-tables.html' },
  { title: 'Ամրագրումների պատմություն', page: 'reserve-history.html', parent: 'Սրահներ / Սեղաններ', parentPage: 'rooms-tables.html' },
  { title: 'Չեկ', page: 'reports-check.html', parent: 'reports', parentPage: 'reports.html' },
  { title: 'Փաստաթղթի մանրամասներ', page: 'store-document-content.html', parent: 'store', parentPage: 'store.html' },
  { title: 'Ձևակերպված փաստաթուղթ', page: 'store-document-submitted.html', parent: 'store', parentPage: 'store.html' },
  { title: 'Հումքի պատմություն', page: 'item.html', parent: 'store', parentPage: 'store.html' },
  { title: 'Ներկայության մանրամասնում', page: 'staff-detailed-presence.html', parent: 'staff', parentPage: 'staff.html' },
];
const activeParentByPage = Object.fromEntries(standalonePages.map(page => [page.page, page.parentPage]));
const activeChildByPage = {
  'reserve-history.html': 'reserve.html',
  'item.html': 'store-history.html',
  'store-document-content.html': 'store-documents.html',
  'store-document-submitted.html': 'store-documents.html',
  'staff-detailed-presence.html': 'staff-present.html',
};

function esc(value) {
  return String(value).replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ch]));
}

function sidebar(currentPage) {
  return nav.map(item => {
    const isActive = item.page === currentPage || item.page === activeParentByPage[currentPage] || (item.children || []).some(child => child.page === currentPage);
    const childHtml = item.children ? `<ul class="sub">${item.children.map(child => `<li><a href="${child.page}" data-page="${child.page}" class="${child.page === currentPage || child.page === activeChildByPage[currentPage] ? 'activated' : ''}">${child.title}</a></li>`).join('')}</ul>` : '';
    const iconHtml = item.page === 'ai-generator.html'
      ? '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="16" height="16" rx="1.52381" fill="#D9D9D9"/><path d="M11.0411 11.7617H9.12444L8.36254 9.77958H4.87444L4.1542 11.7617H2.28516L5.68397 3.03553H7.54706L11.0411 11.7617ZM7.79706 8.30934L6.59468 5.07124L5.41611 8.30934H7.79706ZM11.928 11.7617V3.03553H13.6899V11.7617H11.928Z" fill="#2E2E2E"/></svg>'
      : `<i class="${item.icon || 'fa fa-circle'}"></i>`;
    const badge = item.page === 'ai-generator.html' ? '<span class="badge badge-danger sidebar-new-badge">New</span>' : '';
    const dcjq = item.children ? '<span class="dcjq-icon"></span>' : '';
    return `<li class="${item.children ? 'sub-menu' : ''} ${isActive ? 'active' : ''}">
      <a href="${item.page}" data-page="${item.page}" class="${[item.children ? 'dcjq-parent' : '', isActive ? 'active' : ''].filter(Boolean).join(' ')}">
        ${iconHtml}<span>${item.title}</span>${badge}${dcjq}
      </a>
      ${childHtml}
    </li>`;
  }).join('\n');
}

function head(page) {
  return `<!DOCTYPE html>
<html lang="hy">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
  <title>${esc(page.title)} - SMARTREST template</title>
  <link rel="shortcut icon" href="assets/img/common/favicon.png">
  <link href="assets/common/css/bootstrap.css" rel="stylesheet">
  <link href="assets/common/css/bootstrap-reset.css" rel="stylesheet">
  <link href="assets/font-awesome/css/font-awesome.css" rel="stylesheet">
  <link href="assets/common/css/font-awesome-4.7.0.css" rel="stylesheet">
  <link href="assets/common/css/style.css" rel="stylesheet">
  <link href="assets/common/css/new_style.css" rel="stylesheet">
  <link href="assets/common/css/style-responsive.css" rel="stylesheet">
  <link href="assets/common/css/headerStyles.css" rel="stylesheet">
  ${page.page === 'fast-food.html' ? '<link href="assets/fast_new/css/fast_new_css.css" rel="stylesheet">\n  <link href="assets/common/css/tagsinput.css" rel="stylesheet">\n  <link href="assets/fast_new/css/eMarkModalStyles.css" rel="stylesheet">\n  <link href="assets/fast_new/css/fastMobile.css" rel="stylesheet">\n  <link href="assets/fast_new/css/fast_new.css" rel="stylesheet">' : ''}
  ${page.page === 'index.html' ? '<link href="source/jsCalendar.css" rel="stylesheet">' : ''}
  <link href="assets/main/css/main.css" rel="stylesheet">
  ${page.page === 'index.html' ? '<link href="assets/main/css/owl.carousel.css" rel="stylesheet">' : ''}
  <link href="assets/rooms_tables/css/rooms_tables_index.css" rel="stylesheet">
  <link href="assets/rooms_tables/css/tables.css" rel="stylesheet">
  <link href="assets/rooms_tables/css/new_style.css" rel="stylesheet">
  ${page.page === 'rooms-hall.html' ? '<link href="assets/rooms_tables/css/hall.css" rel="stylesheet">' : ''}
  ${page.page === 'rooms-hall-planning.html' ? '<link href="assets/rooms_tables/css/hall-planning.css" rel="stylesheet">' : ''}
  ${page.page === 'rooms-hall-tables.html' ? '<link href="assets/rooms_tables/css/hall-tables.css" rel="stylesheet">' : ''}
  <link href="assets/store/css/store.css" rel="stylesheet">
  <link href="assets/reports/css/reports.css" rel="stylesheet">
  ${page.page === 'reports-tables-history.html' ? '<link href="assets/reports/css/tablesHistory.css" rel="stylesheet">' : ''}
  ${page.page === 'reports-material-group-history.html' ? '<link href="assets/reports/css/tablesHistory.css" rel="stylesheet">' : ''}
  ${page.page === 'reports-delivery.html' ? '<link href="assets/reports/css/deliveryTable.css" rel="stylesheet">' : ''}
  ${page.page === 'reports-moved-tables.html' ? '<link href="assets/reports/css/movedTables.css" rel="stylesheet">' : ''}
  ${page.page === 'reports-moved-items.html' ? '<link href="assets/reports/css/movedItems.css" rel="stylesheet">' : ''}
  ${page.page === 'reports-food.html' ? '<link href="assets/reports/css/food.css" rel="stylesheet">' : ''}
  ${page.page === 'reports-combo.html' ? '<link href="assets/reports/css/combo.css" rel="stylesheet">' : ''}
  ${page.page === 'reports-ingredients.html' ? '<link href="assets/reports/css/ingredients.css" rel="stylesheet">' : ''}
  ${page.page === 'reports-material-group-history.html' ? '<link href="assets/reports/css/materialGroupHistory.css" rel="stylesheet">' : ''}
  ${page.page === 'reports-logs.html' ? '<link href="assets/reports/css/logs.css" rel="stylesheet">' : ''}
  ${page.page === 'analysis-planning.html' ? '<link href="assets/analysis/css/planning.css" rel="stylesheet">' : ''}
  ${page.page === 'analysis-top-passive.html' ? '<link href="assets/analysis/css/topPassive.css" rel="stylesheet">' : ''}
  ${page.page === 'analysis-order-statistics.html' ? '<link href="assets/analysis/css/orderStatistics.css" rel="stylesheet">' : ''}
  ${page.page === 'analysis-sales-statistics.html' ? '<link href="assets/analysis/css/salesStatistics.css" rel="stylesheet">' : ''}
  ${['cash.html', 'cash-totalized.html', 'cash-settings.html', 'expense-types.html'].includes(page.page) ? '<link href="assets/cash/css/index.css" rel="stylesheet">' : ''}
  ${page.page === 'ai-generator.html' ? '<link href="assets/ai/css/style.css" rel="stylesheet">' : ''}
  ${['menu.html', 'staff.html'].includes(page.page) ? '<link href="assets/select2/select2.min.css" rel="stylesheet">' : ''}
  <link href="assets/template.css" rel="stylesheet">
  ${page.page === 'rooms-hall-tables.html' ? '<link href="assets/rooms_tables/css/hall-tables.css" rel="stylesheet">' : ''}
  ${page.page === 'rooms-printer-error.html' ? '<link href="assets/rooms_tables/css/printerError.css" rel="stylesheet">' : ''}
  ${page.page === 'rooms-fiscal-error.html' ? '<link href="assets/rooms_tables/css/fiscalError.css" rel="stylesheet">' : ''}
  ${page.page === 'rooms-invisible-orders.html' ? '<link href="assets/rooms_tables/css/invisibleOrders.css" rel="stylesheet">' : ''}
  ${page.page === 'rooms-kitchen.html' ? '<link href="assets/rooms_tables/css/kitchen.css" rel="stylesheet">' : ''}
  ${['reserve.html', 'reserve-history.html'].includes(page.page) ? '<link href="assets/reserve/css/reserve.css" rel="stylesheet">' : ''}
</head>`;
}

function languageMenu(id = '') {
  return `<ul class="dropdown-menu"${id ? ` id="${id}" data-lang="hy"` : ''}>
    <li><a href="#"><img src="/static/img/flags/hy.png" alt=""> Հայերեն</a></li>
    <li><a href="#"><img src="/static/img/flags/en.png" alt=""> English</a></li>
    <li><a href="#"><img src="/static/img/flags/ru.png" alt=""> Русский</a></li>
  </ul>`;
}

function headerMarkup() {
  return `<header class="header white-bg" style="padding-bottom:5px">
    <div class="sidebar-toggle-box">
      <div data-url="#" class="icon-reorder tooltips"></div>
    </div>
    <a href="index.html" id="main-logo" class="logo dont-show mec_625">
      <img class="main-logo" width="219" src="/static/img/common/logo_new.png" alt="SMARTREST">
    </a>
    <div class="nav notify-row" id="top_menu">
      <ul class="nav top-menu smart-header-actions" style="margin-top: 6px; display: flex; align-items: center">
        <li id="header_inbox_bar" class="dropdown">
          <a style="color: #2e2e2e" id="mutq_elq_ico" class="dropdown-toggle fa fa-user" data-toggle="dropdown" title="Ներկա-բացակա">
            <span class="badge bg-important" title="Ներկա անձնակազմ">12</span>
          </a>
          <ul class="dropdown-menu extended inbox">
            <div class="notify-arrow notify-arrow-red"></div>
            <li><p class="red">Անձնակազմի մուտք / ելք</p></li>
            <li><input id="card_key" onclick="event.stopPropagation();" data-url="#" type="text" class="form-control sm-input" autofocus=""></li>
            <li><a href="#"><span class="photo"><img alt="avatar" src="/static/img/common/avatar1_small.jpg"></span><span class="subject"><span class="message" style="color:#5cb85c;font-weight:bold;margin-top:-4px">09:18</span><span class="from">Արամ Սարգսյան</span><span class="time"></span></span><span class="message">Մատուցող</span></a></li>
            <li><a href="#"><span class="photo"><img alt="avatar" src="/static/img/common/avatar1_small.jpg"></span><span class="subject"><span class="message" style="color:#5cb85c;font-weight:bold;margin-top:-4px">10:04 <span style="color:#000">-</span> <span style="color:#d43f3a">18:21</span></span><span class="from">Նարե Մկրտչյան</span><span class="time"></span></span><span class="message">Գանձապահ</span></a></li>
          </ul>
        </li>
        <li id="header_notification_bar" class="dropdown">
          <a style="color: #2e2e2e" class="dropdown-toggle fa fa-file" title="Հաշվետվություն / Հերթափոխի ավարտ" data-toggle="dropdown"></a>
          <ul class="dropdown-menu extended notification">
            <div class="notify-arrow notify-arrow-green"></div>
            <li><p class="green">Հաշվետվություն / Օրվա վերջ</p></li>
            <li><input id="admin_pass" autocomplete="off" onclick="event.stopPropagation();" data-url="#" type="text" class="form-control sm-input" placeholder="Ադմինիստրատիվ մուտք"></li>
            <li><a href="#" style="white-space: normal"><button id="print_hashvetvutyun" data-url="#" class="btn btn-danger col-xs-12" data-toast="Հաշվետվությունը ուղարկվեց տպման">Տպել հաշվետվություն</button><div style="clear:both; margin-bottom:5px"></div><i style="color:#f5b400" class="icon-info-sign"></i> Տպել օրվա ընդհանուր կատարած առևտորի հաշվետվությունը</a></li>
            <li><a href="#" style="white-space: normal"><button id="break_point" data-url="#" class="btn btn-info col-xs-12 hashv_orvaverj" data-toggle="modal" data-target="#dayEndModal">Օրվա վերջ</button><div style="clear:both; margin-bottom:5px"></div><i style="color:#f5b400" class="icon-info-sign"></i> Օրվա վաճառքն ու չեքերի քանակը զրոյացնել</a></li>
            <li><a href="#" style="white-space: normal"><button id="printCashBoxReport" class="btn btn-warning col-xs-12" data-url="#">Դրամարկղի Հաշվետվություն</button><div style="clear:both; margin-bottom:5px"></div><i style="color:#f5b400" class="icon-info-sign"></i> Հերթափոխի դրամարկղի օրվա հաշվետվություն</a></li>
          </ul>
        </li>
        <li><a href="client-screen.html" target="_blank"><img src="/static/img/icons/visitorsPage.svg" title="Հաճախորդի էկրան" alt=""></a></li>
        <li><a href="service-system.html"><img src="/static/img/icons/serviceSystem.svg" aria-hidden="true" title="Սպասման համակարգ" alt=""></a></li>
        <li><a href="fast-food-handler.html"><img src="/static/img/icons/readyOrders.svg" aria-hidden="true" title="Պատրաստի պատվերներ" alt=""></a></li>
        <li class="dropdown" id="incoming_order" data-site="#">
          <article id="notification_sound"></article>
          <a data-toggle="dropdown" class="dropdown-toggle"><i style="color: #2e2e2e" class="fa fa-bell" aria-hidden="true" title="Պատվերներ/Կանչեր"></i><span class="badge bg-important" title="Պատվերներ/Կանչեր">3</span></a>
          <ul class="dropdown-menu extended notification"><div class="notify-arrow notify-arrow-red"></div><li><p class="red">Պատվերներ/Կանչեր</p></li><li class="notification-item"><a class="border-warning" href="#">Սեղան 4 սպասարկում է կանչում</a></li></ul>
        </li>
        <li class="dropdown" id="table_reserve">
          <a data-toggle="dropdown" class="dropdown-toggle"><img src="/static/img/icons/calendar.svg" aria-hidden="true" title="Ամրագրված սեղաններ" alt=""><span class="badge bg-warning bg-pulse" title="Ամրագրված սեղաններ">2</span></a>
          <ul class="dropdown-menu extended reserve"><div class="notify-arrow notify-arrow-yellow"></div><li><p class="yellow">Ամրագրված սեղաններ</p></li><li class="notification-item"><a class="border-warning" href="reserve.html"><span class="label label-warning"><i class="icon-calendar"></i></span> Սեղան 8 / Main Hall <span class="pull-right">12ր.</span></a></li><li><a href="reserve.html">Տեսնել բոլորը</a></li></ul>
        </li>
        <li class="dropdown" id="birthdays">
          <a data-toggle="dropdown" class="dropdown-toggle"><i class="icon-gift"></i><span class="badge bg-warning bg-pulse" title="Ծնունդներ">1</span></a>
          <ul class="dropdown-menu extended reserve"><div class="notify-arrow notify-arrow-yellow"></div><li><p class="yellow">Ծնունդներ</p></li><li class="notification-item"><a class="border-warning" href="clients.html"><span class="label label-warning"><i class="icon-gift"></i></span><span class="pull-right">Անի (29)</span></a></li></ul>
        </li>
        <li class="dropdown" id="table_ecommerce" data-wav_path="/static/img/common" data-href="#">
          <a data-toggle="dropdown" class="dropdown-toggle"><img src="/static/img/icons/delivery_header.png" width="22" alt=""><span class="badge bg-warning bg-pulse" id="ecOrderCount" title="Առցանց պատվերներ">4</span></a>
          <ul class="dropdown-menu extended reserve" id="ecommerceNotificationList"><div class="notify-arrow notify-arrow-yellow"></div><li><p class="yellow">Առցանց պատվերներ</p></li><li class="notification-item"><a class="border-warning" href="ecommerce.html">#1024 2026-07-02 14:20</a></li><li class="see-all"><a href="ecommerce.html">Տեսնել բոլորը</a></li></ul>
        </li>
        <li class="dropdown mt-05" id="alert-error" data-site-url="#">
          <a class="dropdown-toggle" href="rooms-invisible-orders.html"><img title="Ընթացիկ հաշիվներ" style="width: 18px" src="/static/img/icons/order-icon.png" alt=""><span class="badge bg-important" title="Ընթացիկ հաշիվներ" data-badge>5</span></a>
        </li>
      </ul>
    </div>
    <div class="top-nav ">
      <ul class="nav pull-right top-menu new_top_menu">
        <li class="dropdown language lang_mob">
          <a data-close-others="true" data-hover="dropdown" data-toggle="dropdown" class="dropdown-toggle" href="#"><img src="/static/img/flags/hy.png" alt="hy"><span class="username"></span><b class="caret"></b></a>
          ${languageMenu('dropDownLanguage')}
        </li>
        <li class="dropdown language lang_desk" id="lang">
          <a data-close-others="true" data-hover="dropdown" data-toggle="dropdown" class="dropdown-toggle" href="#"><img src="/static/img/flags/hy.png" alt="hy"><span class="username"></span><b class="caret"></b></a>
          ${languageMenu()}
        </li>
        <li class="license_new"><a href="#" title="լիցենզը լրանում է 27 օրից"><img src="/static/assets/main/image/lice.png" alt=""></a></li>
        <li class="dropdown">
          <a data-toggle="dropdown" class="dropdown-toggle company_name" href="#"><img alt="" src="/static/img/common/avatar1_small.jpg"><span class="username">SMART REST (ADMIN)</span><b class="caret"></b></a>
          <ul class="dropdown-menu extended logout">
            <div class="log-arrow-up"></div>
            <li style="width:47%" class="float-left"><a href="profile.html"><i class="icon-suitcase"></i> Պրոֆիլ</a></li>
            <li><a href="#"><i class="icon-key"></i> Ելք համակարգից</a></li>
          </ul>
        </li>
        <li class="information"><a href="#" data-toggle="modal" data-target="#helperModal"><i class="fa fa-question" aria-hidden="true"></i></a></li>
      </ul>
    </div>
  </header>`;
}

function layout(page, content) {
  return `${head(page)}
<body class="template-body" data-page="${page.page}">
<section id="container">
  ${headerMarkup()}
  <aside><div id="sidebar" class="nav-collapse"><ul class="sidebar-menu" id="nav-accordion">${sidebar(page.page)}</ul></div></aside>
  <section id="main-content" class="${page.page === 'fast-food.html' ? 'fastNew' : ''}"><section class="wrapper">${content}</section></section>
</section>
${commonModals(page)}
<script src="assets/common/js/jquery.js"></script>
<script src="assets/common/js/bootstrap.min.js"></script>
${['menu.html', 'staff.html'].includes(page.page) ? '<script src="assets/select2/select2.min.js"></script>' : ''}
<script src="assets/template.js"></script>
${page.page === 'menu.html' ? `<script>
  (function () {
    if (!window.jQuery || !jQuery.fn.select2) return;
    jQuery(function () {
      jQuery('#happy_days').select2({ width: '100%' });
    });
  })();
</script>` : ''}
</body>
</html>`;
}

function dashboardContent() {
  const staff = [
    ['matucox(uhi).png', 4, 'Մատուցող'],
    ['xoharar.png', 2, 'Խոհարար'],
    ['barmen.png', 2, 'Բարմեն'],
    ['menejer.png', 1, 'Մենեջեր'],
    ['hashvapah.png', 0, 'Հաշվապահ'],
    ['araqich.png', 1, 'Առաքիչ'],
    ['havaqarar.png', 1, 'Հավաքարար'],
    ['anvtangutyun.png', 1, 'Անվտանգություն'],
  ];

  return `<div class="main_dashboard">
  <div class="time_date_main">
    <div class="time_date">
      <div class="time">
        <div class="wallClock">
          <span>3</span>
          <span>6</span>
          <span>9</span>
          <span>12</span>
          <div class="centerDot"></div>
          <div class="hourHand"><div class="hand"></div></div>
          <div class="minuteHand"><div class="hand"></div></div>
          <div class="secondHand"><div class="hand"></div></div>
        </div>
        <div class="dateAndTimeContainer">
          <div id="time">12:00:00</div>
          <div class="dateControl">
            <span class="arrowPrevious"> &lt; </span>
            <div class="currentDate">02.07.2026</div>
            <span class="arrowNext"> &gt; </span>
          </div>
        </div>
      </div>
      <input type="hidden" value='["2026-07-03","2026-07-18"]' id="reserved_dates">
      <input type="hidden" value="reserve.html" id="reserve_href">
      <div>
        <div class="informerCalendar jsCalendar">
          <table>
            <thead>
              <tr class="jsCalendar-title-row">
                <th colspan="7" class="jsCalendar-title">
                  <div class="jsCalendar-title-left"><div class="jsCalendar-nav-left"></div></div>
                  <div class="jsCalendar-title-name">Հուլիս</div>
                  <div class="jsCalendar-title-right"><div class="jsCalendar-nav-right"></div></div>
                </th>
              </tr>
              <tr class="jsCalendar-week-days">
                <th>Երկ</th><th>Երք</th><th>Չրք</th><th>Հնգ</th><th>Ուրբ</th><th>Շբթ</th><th>Կիր</th>
              </tr>
            </thead>
            <tbody>
              <tr><td class="jsCalendar-previous">29</td><td class="jsCalendar-previous">30</td><td>1</td><td class="jsCalendar-current">2</td><td class="jsCalendar-reserved">3</td><td>4</td><td>5</td></tr>
              <tr><td>6</td><td>7</td><td>8</td><td>9</td><td>10</td><td>11</td><td>12</td></tr>
              <tr><td>13</td><td>14</td><td>15</td><td>16</td><td>17</td><td class="jsCalendar-reserved">18</td><td>19</td></tr>
              <tr><td>20</td><td>21</td><td>22</td><td>23</td><td>24</td><td>25</td><td>26</td></tr>
              <tr><td>27</td><td>28</td><td>29</td><td>30</td><td>31</td><td class="jsCalendar-next">1</td><td class="jsCalendar-next">2</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <div class="staff_in"><div><h2>Ներկա անձնակազմ</h2><div class="staff_main">${staff.map(([img, count, title]) => `<div class="staff_ ${count > 0 ? 'is_present' : ''} tooltip1 expand" data-title="${title}"><div class="staff_count" title="${title}">${count}</div><img src="assets/main/image/positions/${img}" alt=""></div>`).join('')}</div></div></div>
  ${materialsBlock('Վերջացող հումքեր', ['ՀՈՒՄՔ','ՊԱՀԵՍՏ','Նվազ․ ՄՆԱՑՈՐԴ','ՄՆԱՑՈՐԴ'], [['Լոլիկ կգ','Գլխավոր','8','3.5'],['Ֆրի կգ','Խոհանոց','12','7'],['Սուրճ կգ','Բար','4','1']])}
  ${priceBlock()}
  ${topSaleBlock('Թոփ վաճառք', 'top_sale')}
  ${topSaleBlock('Պասիվ վաճառք', 'top_sale passive_sale')}
</div>`;
}

function materialsBlock(title, heads, rows) {
  return `<div class="finishing_materials"><h2>${title}</h2><div class="materials_main"><div class="materials_subheader"><p class="material_name"><span>${heads[0]}</span></p><p class="material_store"><span>${heads[1]}</span></p><p class="material_min_balance"><span>${heads[2]}</span></p><p class="material_balance"><span>${heads[3]}</span></p></div><div class="materials_main_content">${rows.map(row => `<div class="materials_content"><div class="material_name"><span>${row[0]}</span></div><div class="material_store"><span>${row[1]}</span></div><div class="material_min_balance"><span>${row[2]}</span></div><div class="material_balance"><span>${row[3]}</span></div></div>`).join('')}</div></div></div>`;
}

function priceBlock() {
  return `<div class="finishing_materials max_price"><h2>Գինը գերազանցած ապրանքներ</h2><div class="materials_main"><div class="materials_subheader"><p class="good"><span>ԱՊՐԱՆՔ</span></p><p class="self_price"><span>ԻՆՔՆԱՐԺԵՔ</span></p><p class="price_"><span>ԳԻՆ</span></p></div><div class="materials_main_content"><div class="materials_content"><div class="good"><span>Կոկտեյլ</span></div><div class="self_price"><span>1800</span></div><div class="price_"><span>1500</span></div></div><div class="materials_content"><div class="good"><span>Սթեյք</span></div><div class="self_price"><span>4200</span></div><div class="price_"><span>3900</span></div></div></div></div></div>`;
}

function topSaleBlock(title, cls) {
  return `<div class="finishing_materials ${cls}"><h2>${title}</h2><div class="materials_main"><div class="materials_main_content"><div class="materials_content"><div class="top_good"><img src="assets/img/uploads/kitchen_def_logo.svg" alt=""><a>Քլաբ սենդվիչ</a></div><div class="top_good"><img src="assets/img/uploads/kitchen_def_logo.svg" alt=""><a>Լատե</a></div></div><div class="materials_content"><div class="top_good"><img src="assets/img/uploads/kitchen_def_logo.svg" alt=""><a>Սեզար</a></div><div class="top_good"><img src="assets/img/uploads/kitchen_def_logo.svg" alt=""><a>Բուրգեր</a></div></div></div></div></div>`;
}

function fastProductCard(item) {
  return `<article class="media product-article" data-id="${item.id}" data-emark="0" data-barcode="${item.barcode}">
    ${item.suspended ? '<div class="disabled-layer"><div><h3>Վաճառքը Կասեցված է</h3></div></div>' : ''}
    <a class="pull-left thumb p-thumb addContent pointer defImage" style="background-image: url('/static/img/uploads/kitchen_def_logo.svg')">
      <span class="itemSelectedQuantity ${item.selected ? '' : 'notSelected'}" id="itemSelectedQuantity-${item.id}" data-id="${item.id}">${item.selected || ''}</span>
    </a>
    <div class="media-body">
      <span class="text-muted hidden">#<span class="addItemId">${item.id}</span></span>
      <div>
        <a class="pull-left thumb p-thumb addContent pointer"><span class="p-head addItemName"><span>${item.name}</span></span></a>
        <span class="text-muted"><span class="addItemPrice">${item.price}</span> ֏</span>
      </div>
      <div class="input-group product_spinner">
        <button class="decrease">-</button>
        <input type="number" class="form-control input-sm addItemCount inline-block" value="1">
        <button class="increase">+</button>
      </div>
    </div>
  </article>`;
}

function fastFoodContent() {
  const groups = [
    {
      place: 'Խոհանոց',
      icon: 'fa fa-cutlery',
      active: true,
      menus: [
        { id: 11, title: 'Աղցաններ', active: true },
        { id: 12, title: 'Տաք ուտեստներ' },
        { id: 13, title: 'Փաթեթներ' },
      ],
      items: [
        { id: 101, name: 'Սեզար աղցան', price: 2900, barcode: '100101', selected: 2 },
        { id: 102, name: 'Քլաբ սենդվիչ', price: 2400, barcode: '100102' },
        { id: 103, name: 'Բուրգեր հավի մսով', price: 2200, barcode: '100103' },
        { id: 104, name: 'Ֆրի կարտոֆիլ', price: 900, barcode: '100104' },
        { id: 105, name: 'Պիցցա Մարգարիտա', price: 3400, barcode: '100105' },
        { id: 106, name: 'Լանչ փաթեթ', price: 3900, barcode: '100106', suspended: true },
      ],
    },
    {
      place: 'Բար',
      icon: 'fa fa-coffee',
      menus: [
        { id: 21, title: 'Սուրճ' },
        { id: 22, title: 'Ըմպելիքներ' },
      ],
      items: [
        { id: 201, name: 'Ամերիկանո', price: 900, barcode: '200201' },
        { id: 202, name: 'Լատե', price: 1200, barcode: '200202', selected: 1 },
        { id: 203, name: 'Թարմ հյութ', price: 1600, barcode: '200203' },
        { id: 204, name: 'Թեյ հատապտղային', price: 1000, barcode: '200204' },
      ],
    },
  ];

  const tabs = groups.map(group => `<li role="presentation" class="text-center dropdown ${group.active ? 'active' : ''}">
    <a href="#" class="removefind dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
      <i class="${group.icon}"></i>
      ${group.place} <img class="dropDownArrow" src="/static/img/icons/dropDownArrow.svg" alt="">
    </a>
    <ul class="dropdown-menu" aria-labelledby="myTabDrop1" id="myTabDrop1-contents">
      ${group.menus.map(menu => `<li class="${menu.active ? 'active' : ''} text-center"><a href="#menu${menu.id}" data-toggle="tab">${menu.title}</a></li>`).join('')}
    </ul>
  </li>`).join('');

  const panes = [
    `<div id="menu11" class="tab-pane active">${groups[0].items.map(fastProductCard).join('')}</div>`,
    `<div id="menu12" class="tab-pane">${groups[0].items.slice(0, 4).reverse().map(item => fastProductCard({ ...item, selected: 0, suspended: false })).join('')}</div>`,
    `<div id="menu13" class="tab-pane">${groups[0].items.slice(2, 6).map(item => fastProductCard({ ...item, selected: 0 })).join('')}</div>`,
    `<div id="menu21" class="tab-pane">${groups[1].items.map(fastProductCard).join('')}</div>`,
    `<div id="menu22" class="tab-pane">${groups[1].items.slice().reverse().map(item => fastProductCard({ ...item, selected: 0 })).join('')}</div>`,
    `<div id="day_menu" class="tab-pane">${[groups[0].items[0], groups[0].items[2], groups[1].items[1]].map(item => fastProductCard({ ...item, selected: 0 })).join('')}</div>`,
  ].join('');

  return `<div class="row">
  <div class="col-xs-12 col-md-8 col-lg-8 mainContentContainer">
    <div class="input-group m-bot15 topInputsContainer">
      <input type="text" class="form-control add_using_barcode input-sm" name="command" placeholder="Կարճ հրահանգ" id="command">
      <input type="text" style="margin-left:10px;border-radius:3px" data-not-found="Ապրանք չի գտվել" data-format-error="Բառկոդի ֆորմատը սխալ է" class="form-control add_quantity_using_barcode input-sm" name="commandBarcode" placeholder="Ավելացնել ապրանք կշեռքի բառկոդով" id="commandBarcode">
    </div>
    <section class="panel">
      <header class="panel-heading menuContainer">
        <ul class="nav nav-tabs fast_food_menu">
          <li class="dayMenu removefind"><a href="#day_menu" data-toggle="tab">Օրվա մենյու</a></li>
          ${tabs}
        </ul>
        <div class="totalContainer"><span>Ընդհանուր արժեք</span><span class="totalPrice">8200</span></div>
        <button class="columnsCountChanger"><span class="selected">2</span><span class="topNumber">3</span><span>4</span></button>
      </header>
      <div id="add_product" style="display:none"></div>
      <div class="panel-body article_items hiddenmenu">
        <div class="menusContainer tab-content add_scroll">${panes}</div>
      </div>
    </section>
  </div>
  <div class="col-lg-4 col-md-4 col-xs-12 dynamic ordersInfoContainer" id="ordersInfoContainerId">
    <ul id="ordersTabs" class="nav nav-tabs">
      <button class="btn btn-success btn-sm add_new_queue"><img src="/static/img/icons/plusIcon.svg" alt=""></button>
      <li onclick="tabsChange(this)" class="active"><a href="#order1" data-toggle="tab" class="pull-left editOrder">Հերթ 1</a></li>
      <li onclick="tabsChange(this)"><a href="#order2" data-toggle="tab" class="pull-left editOrder">Հերթ 2</a></li>
    </ul>
    <div class="panel panel-body" id="ordersContentsParent">
      <div id="ordersContents" class="tab-content">
        <div class="tab-pane fade in active static" id="order1" data-queue="1">
          <div class="special-scroll">
            <table class="table table-hover no-margin">
              <thead><tr><th class="hidden">#</th><th>Անվանում</th><th>Քանակ</th><th>Գին</th><th class="td_del"><i class="icon-cogs"></i></th></tr></thead>
              <tbody data-total="8200">
                <tr data-menu-item-id="101" class="pointer"><td class="td_title">Սեզար աղցան</td><td class="td-count" data-init="2"><input tabindex="-1" type="number" disabled value="2"></td><td class="td-price">5800</td><td><button class="btn btn-danger btn-sm btn-delete-item inTableIconButton" disabled><img src="/static/img/icons/trash.svg" alt=""></button></td></tr>
                <tr data-menu-item-id="202" class="pointer"><td class="td_title">Լատե</td><td class="td-count" data-init="1"><input tabindex="-1" type="number" disabled value="1"></td><td class="td-price">1200</td><td><button class="btn btn-danger btn-sm btn-delete-item inTableIconButton" disabled><img src="/static/img/icons/trash.svg" alt=""></button></td></tr>
                <tr data-menu-item-id="104" class="pointer"><td class="td_title">Ֆրի կարտոֆիլ</td><td class="td-count" data-init="1"><input tabindex="-1" type="number" disabled value="1"></td><td class="td-price">900</td><td><button class="btn btn-danger btn-sm btn-delete-item inTableIconButton" disabled><img src="/static/img/icons/trash.svg" alt=""></button></td></tr>
              </tbody>
            </table>
          </div>
          ${fastOrderSummary('8200', '10', '7380')}
        </div>
        <div class="tab-pane fade static" id="order2" data-queue="2">
          <div class="special-scroll"><table class="table table-hover no-margin"><thead><tr><th class="hidden">#</th><th>Անվանում</th><th>Քանակ</th><th>Գին</th><th class="td_del"><i class="icon-cogs"></i></th></tr></thead><tbody data-total="0"></tbody></table></div>
          ${fastOrderSummary('0', '0', '0')}
        </div>
      </div>
    </div>
  </div>
</div>
<div id="ordersPanelForMobile"><div class="emptySpace"></div><div class="ordersControlPanel"><div class="ordersQueue"><div class="ordersContainer"></div></div><button class="addNewOrder">+</button></div><div class="fastMoveButton"></div></div>
${fastFoodModals()}`;
}

function fastOrderSummary(total, sale, balance) {
  return `<div class="weather-category twt-category no-margin"><ul><li><span>Հաշիվ</span><h5 class="orderTotal static">${total}</h5></li><li><div class="input-group"><span>Զեղչ</span><input type="number" name="orderSale" class="form-control input-sm orderSale static" disabled value="${sale}"></div><div class="input-group"><span>Ստացա</span><input type="number" class="form-control input-sm orderPayed" value="0" autocomplete="off"></div></li><li><span>Մնացորդ</span><h5 class="orderBalance">${balance}</h5></li></ul></div>
  <div class="print_hdm"><input type="checkbox" class="cancelFiscal-btn" checked><label>Տպել ՀԴՄ</label></div>
  <a style="font-size:15px" class="btn btn-warning justPrint btn-block toDisable">Տպել կրկնօրինակը (0)</a>
  <input type="hidden" class="client-id" value="">
  <div class="btn_"><a class="cashbox btn btn-info toDisable sendOrder pay_order_without_print_check r-border">Վճարել Դրամարկղ</a><a class="cashbox btn btn-info sendOrder pay_order_without_print_check r-border print-and-continue">Վճարել Բանկ</a></div>
  <a data-toggle="modal" data-target="#adminModal" class="btn btn-default modal_btn adminLogIn">Ադմինիստրատիվ մուտք</a>
  <button type="button" class="btn btn-block btn-warning check_idram text-white" id="check_idram">Ստուգել <img src="/static/img/icons/idramLogo.svg" alt=""> դրամապանակը</button>
  <button type="button" class="btn btn-block btn-warning check_evoca text-white" id="check_evoca">Ստուգել Evoca վճարումը</button>
  <button type="button" class="btn btn-block btn-warning checkTelCell text-white animate_yellow" id="checkTelCellFastFood">Ստուգել <img src="/static/img/icons/telcellBlackIcon.svg" alt=""> դրամապանակը</button>
  <div class="bonus_main_section" style="margin-top:5px"><div class="input-group charge_cart"><input type="text" class="form-control client_card" placeholder="Բոնուս քարտ" autocomplete="off"><span class="input-group-btn"><button class="btn btn-white btn_remove_client_stuff inTableIconButton" type="button"><img src="/static/img/icons/trash.svg" alt=""></button></span></div><div class="bonus_card_info no-m"></div></div>
  <div class="emptySpaceForPanel"></div>`;
}

function fastFoodModals() {
  return `<div id="eMarkModal"><div class="modalWindow"><div class="modalHeaderContainer"></div><div class="modalContentContainer"></div><div class="modalFooterContainer"><button class="eMarkModalCloseButton">Փակել</button><button class="eMarkModalSubmitButton">Հաստատել</button></div></div></div>
<div id="orderDetailsModal"><div class="modalWindow"><div class="modalHeaderContainer"><div class="tabButtonsContainer"></div></div><div class="modalContentContainer"><div class="tabsContainer"></div></div><div class="modalFooterContainer"><button class="modalCloseButton">Փակել</button><button class="modalSubmitButton">Հաստատել</button></div></div></div>
<div id="bankModal" class="closed"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h4 class="modal-title" style="font-weight:bold">Բանկային</h4></div><div class="modal-body"><h4 class="bank_modal_text">Խնդրում ենք անցկացնել քարտը</h4><img src="/static/img/icons/handWithCard.svg" alt=""></div><div class="modal-footer"><button class="btn btn-default" id="try_again">Կրկին փորձել</button><button class="btn btn-danger" id="terminate_order">Չեղարկել</button></div></div></div></div>
<div aria-hidden="true" aria-labelledby="myModalLabel" role="dialog" id="adminModal" class="modal fade"><div class="modal-dialog modal_left_and_top_padding40"><div class="modal-content"><div class="modal-header"><h4 class="modal-title" style="font-weight:bold">Ադմինիստրատիվ մուտք</h4></div><div class="modal-body"><form class="form-inline" role="form" style="font-size:20px;text-align:center" onsubmit="return false" autocomplete="off"><p>Ադմինիստրատիվ մուտք գործելու դեպքում դուք կարող եք ջնջել կամ ավելացնել նոր պատվերներ, ինչպես նաև զեղչել սեղանի հաշիվը</p><div class="form-group" style="width:100%;margin:10px auto"><label class="sr-only" for="exampleInputPassword5">Ադմինիստրատորի գաղտնաբառ</label><div class="numpad_main"><input tabindex="1" type="text" class="form-control sm-input" id="exampleInputPassword5" name="spacial" placeholder="Ադմինիստրատորի գաղտնաբառ"></div></div><input disabled id="admin_but" type="button" value="Հաստատել" class="btn btn-success" data-dismiss="modal"></form></div></div></div></div>`;
}

function roomsContent() {
  const halls = [
    {
      id: 1,
      name: 'Դրսի սրահ',
      color: '#008000',
      rgb: '0,128,0',
      tables: [
        { number: '1', color: 'green', status: 'ազատ', staff: '', icons: [{ icon: 'money.svg', value: '0' }] },
        { number: '2', color: 'yellow', status: 'սկսված', staff: 'Արամ', time: '00:24', icons: [{ icon: 'money.svg', value: '14200' }] },
        { number: '3', color: 'blue', status: 'ընթացք', staff: 'Նարե', time: '01:08', client: 'Հաճ: Սոնա', icons: [{ icon: 'precent.png', value: '10' }, { icon: 'time.png', value: '3.5' }] },
        { number: '4', color: 'red', status: 'հաշիվ', staff: 'Գոռ', time: '00:12', delivery: true, icons: [{ icon: 'fix.png', value: '8200' }] },
        { number: '5', color: 'green', status: 'ազատ', staff: '', icons: [{ icon: 'money.svg', value: '0' }] },
        { number: '6', color: 'yellow', status: 'սկսված', staff: 'Սոնա', time: '00:43', productSale: true, icons: [{ icon: 'money.svg', value: '8700' }] },
        { number: 'VIP', color: 'blue', status: 'ընթացք', staff: 'Մարի', time: '02:10', icons: [{ icon: 'money.svg', value: '23800' }, { icon: 'precent.png', value: '5' }] },
        { number: '8', color: 'red', status: 'հաշիվ', staff: 'Դավիթ', time: '00:06', icons: [{ icon: 'money.svg', value: '5100' }] }
      ]
    },
    {
      id: 2,
      name: 'Երկրորդ հարկ',
      color: '#0000ff',
      rgb: '0,0,255',
      tables: [
        { number: 'T1', color: 'green', status: 'ազատ', staff: '', icons: [{ icon: 'money.svg', value: '0' }] },
        { number: 'T2', color: 'yellow', status: 'սկսված', staff: 'Հայկ', time: '00:31', icons: [{ icon: 'money.svg', value: '6400' }] },
        { number: 'T3', color: 'green', status: 'ազատ', staff: '', icons: [{ icon: 'time.png', value: '2.0' }] },
        { number: 'T4', color: 'blue', status: 'ընթացք', staff: 'Լիլիթ', time: '01:15', icons: [{ icon: 'money.svg', value: '11900' }] }
      ]
    },
    {
      id: 3,
      name: 'Առաջին հարկ',
      color: '#e600a9',
      rgb: '230,0,169',
      tables: [
        { number: 'V1', color: 'green', status: 'ազատ', staff: '', icons: [{ icon: 'money.svg', value: '0' }] },
        { number: 'V2', color: 'red', status: 'հաշիվ', staff: 'Աննա', time: '00:18', icons: [{ icon: 'money.svg', value: '18400' }] }
      ]
    }
  ];

  const tableCard = table => {
    const time = table.status !== 'ազատ' && table.time
      ? `<div><img src="/static/img/common/clock.png" height="25" alt=""><span class="time"> ${table.time}</span></div>`
      : '';
    const icons = table.icons.map(item => `<div><img src="/static/img/icons/${item.icon}" style="height:20px" alt=""> ${item.value}</div>`).join('');
    const delivery = table.delivery ? '<img src="/static/img/common/del.png" style="height:25px" alt=""> delivery' : '';
    const saleClass = table.productSale ? ' productSale' : '';
    return `<div class="col-xs-12 col-sm-6 symbol_group col-md-3 col-lg-2 w4 px-2 pl${saleClass}" style="margin-bottom: 0.4rem;">
      <section data-table_id="${table.number}" class="panel table_panel border_${table.color}${table.delivery ? ' delivery' : ''}" style="height: 133px; cursor:pointer" onclick="window.location.href='rooms-table-order.html'">
        <div class="symbol ${table.color}">
          <div class="time_main">${time}</div>
          <div><span class="table_number table_name">${table.number}</span></div>
        </div>
        <div class="value symbol_content">
          <div>
            <div class="status_name">
              <p style="font-size:14px; margin-top:1px" class="font_${table.color}">${table.status}</p>
              ${table.staff ? `<p class="staff_name" style="font-size:18px; color:#acacac">${table.staff}</p>` : ''}
            </div>
            ${table.client ? `<div class="status_name"><p title="Անուն: ${table.client.replace('Հաճ: ', '')}" class="font_${table.color} client_name">${table.client}</p></div>` : ''}
          </div>
          <div class="table_icons">${icons}${delivery}</div>
        </div>
      </section>
    </div>`;
  };

  const hallButtons = halls.map(hall => `<a href="#collapse${hall.id}" data-hall-target="#collapse${hall.id}" style="color:#ffffff; background:${hall.color}" class="btn">${hall.name}</a>`).join('');
  const hallPanels = halls.map(hall => `<div class="panel panel-default">
    <div class="panel-heading" style="background:${hall.color}">
      <h4 class="panel-title">
        <a href="#collapse${hall.id}" data-hall-toggle="#collapse${hall.id}" style="display:block; color:#fff">${hall.name}<i style="font-size:20px; float:right" class="icon-caret-down"></i></a>
      </h4>
    </div>
    <div id="collapse${hall.id}" class="panel-collapse collapse in">
      <div class="panel-body" style="background-color:rgba(${hall.rgb},0.5)">
        <div class="row state-overview no-gutters all_halls tablesContainer" style="position:relative;padding-left:4px;padding-top:4px;">
          ${hall.tables.map(tableCard).join('')}
        </div>
      </div>
    </div>
  </div>`).join('');

  return `<div class="rooms-tables-page">
    <div class="row">
      <div class="col-xs-12" id="transform_alert" style="display:none; height:0">
        <div class="alert alert-warning fade in" style="font-size:18px">
          <strong><i class="icon-exclamation-sign"></i></strong> Ընտրեք այն սեղանը, որի վրա ուզում եք տեղափոխել նշված սեղանը
          <a class="btn btn-warning btn-sm">Չեղարկել</a>
        </div>
      </div>
      <div class="col-lg-12 col-sm-12 col-xs-12 mb-5px rooms-hall-buttons">
        ${hallButtons}
      </div>
      <div class="col-lg-8 rooms-waiter-row" data-backend-note="conditional waiter selector: keep for backend configuration">
        <button class="waiter_out btn btn-default">Նեյտրալ <i class="icon-lock"></i></button>
        <select class="form-control w-183px-media" style="width: 300px" id="waiter_id">
          <option>Արամ Սարգսյան</option>
          <option>Նարե Մկրտչյան</option>
          <option disabled selected>Ընտրեք մատուցողին</option>
        </select>
      </div>
    </div>
    <div style="clear:both"></div>
    <div class="panel-group scroll_design" id="accordion" style="overflow:auto; margin-top:10px">
      ${hallPanels}
    </div>
  </div>
  <div aria-hidden="true" aria-labelledby="myModalLabel" role="dialog" tabindex="-1" id="myModal-1" class="modal fade">
    <div class="modal-dialog modal_left_and_top_padding40"><div class="modal-content"><div class="modal-header"><h4 class="modal-title" style="font-weight:bold;"></h4></div><div class="modal-body" style="text-align:center;"><div class="panel-body"><form class="form-horizontal"><fieldset title="Քայլ 1" class="step" id="default-step-0"><legend>Կցե՞լ սեղանին մատուցող</legend></fieldset><input id="ayo" type="button" class="finish btn btn-danger" value="Հաստատել"></form></div></div></div></div>
  </div>
  <div aria-hidden="true" aria-labelledby="myModalLabel" role="dialog" tabindex="-1" id="myModal-4" class="modal fade">
    <div class="modal-dialog modal_left_and_top_padding40"><div class="modal-content"><div class="modal-header"><h4 class="modal-title" style="font-weight:bold;">Տեղափոխում</h4></div><div class="modal-body" style="text-align:center;"><div class="panel-body"><form class="form-horizontal"><label class="col-sm-12 control-label" style="font-size:20px; text-align:center">Հաստատեք սեղանի տեղափոխումը</label><div class="col-sm-12 panel-body" id="transform_staff" style="padding:0"><p style="font-size:20px">և ընտրեք սպասարկողին</p><div class="btn-group" data-toggle="buttons" style="width:100%"><label class="btn btn-info active" style="width:50%"><input type="radio" id="new_staff"><span>Նոր</span></label><label class="btn btn-info" style="width:50%"><input type="radio" id="old_staff"><span>Հին</span></label></div></div><input id="ayo4" style="float:none; margin-top:15px" type="button" class="finish btn btn-success" value="Հաստատել"></form></div></div></div></div>
  </div>`;
}

function orderContent() {
  const orderRows = [
    {
      name: 'Սեզար աղցան',
      count: 2,
      price: 2900,
      sale: 0,
      total: 5800,
      time: '12:34:10',
      staff: 'Արամ Սարգսյան',
      comment: 'Առանց սոխի'
    },
    {
      name: 'Լատե',
      count: 1,
      price: 1200,
      sale: 0,
      total: 1200,
      time: '12:37:02',
      staff: 'Նարե Մկրտչյան'
    },
    {
      name: 'Խնձորի ֆրեշ',
      count: 2,
      price: 900,
      sale: 10,
      total: 1620,
      time: '12:39:41',
      staff: 'Նարե Մկրտչյան',
      without: 'Սառույց'
    }
  ];

  const rows = orderRows.map((item, index) => {
    const productRow = `<tr data-content="${index + 1}" data-name="${item.name}" data-price="${item.price}" data-sale="${item.sale}">
      <td><input type="checkbox" name="item_to_transfer" style="width:20px;height:16px"> ${item.name}</td>
      <td>
        <div class="tb_btns">
          <div>
            <button class="btn btn-sm btn-warning menu_item_minus"><i class="glyphicon glyphicon-minus"></i></button>
            <span class="counter">${item.count}</span>
            <button class="btn btn-sm btn-success menu_item_plus"><i class="glyphicon glyphicon-plus"></i></button>
          </div>
          <button class="btn btn-sm btn-success pull-right menu_item_edit_submit toDisable" disabled><i class="glyphicon glyphicon-ok"></i></button>
        </div>
      </td>
      <td class="hidden-sm item-price">${item.price}</td>
      <td class="hidden-sm item-sale">${item.sale}</td>
      <td class="item-total">${item.total}</td>
      <td>${item.time}</td>
      <td>${item.staff}</td>
      <td>
        <div>
          <button class="btn btn-xs btn-success to_sale_product inTableIconButton" title="Զեղչ" data-toggle="modal" data-target="#saleProductModal"><span style="font-weight:bolder !important">%</span></button>
          <button class="btn btn-xs btn-danger menu_item_delete inTableIconButton"><img src="assets/img/icons/trash.svg" alt=""></button>
          <button class="btn btn-xs btn-success set_staff inTableIconButton" title="Վաճառող" data-toggle="modal" data-target="#setStaffModal"><i class="icon-user"></i></button>
        </div>
      </td>
    </tr>`;
    const withoutRow = item.without ? `<tr class="order-extra-row"><td colspan="2" class="border-l-white"></td><td colspan="1">Առանց</td><td colspan="2">${item.without}</td><td colspan="3"></td></tr>` : '';
    const commentRow = item.comment ? `<tr class="order-extra-row"><td colspan="8" class="commentTd border-l-white"><span style="margin-right:10px">Մեկնաբանություն</span> ${item.comment}</td></tr>` : '';
    return productRow + withoutRow + commentRow;
  }).join('');

  return `<div class="order-page delivery_section col-md-12">
    <div class="col-sm-8 col-xs-7" id="down_side">
      <section class="panel">
        <div class="panel-body">
          <header class="panel-heading text-center">
            <h3>Պատվիրված մթերք</h3>
          </header>
          <input type="hidden" id="admin_log_sign" value="true">
          <div class="adv-table" data-order="128" id="subtablesContainer">
            <table class="table table-hover subtable" data-subtable="1">
              <thead>
                <tr>
                  <th>Անվանում</th>
                  <th>Քանակ</th>
                  <th class="hidden-sm">Արժեք</th>
                  <th class="hidden-sm">Զեղչ</th>
                  <th>Ընդհանուր</th>
                  <th>date_jam</th>
                  <th>Վաճառող</th>
                  <th><img src="assets/img/icons/gear.svg" alt=""></th>
                </tr>
              </thead>
              <tbody>${rows}</tbody>
            </table>
            <table class="table table-hover subtable" data-subtable="2">
              <thead>
                <tr>
                  <th>Անվանում</th>
                  <th>Քանակ</th>
                  <th class="hidden-sm">Արժեք</th>
                  <th class="hidden-sm">Զեղչ</th>
                  <th>Ընդհ․</th>
                  <th>Ժամ</th>
                  <th>Ջնջ</th>
                  <th><img src="assets/img/icons/gear.svg" alt=""></th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </section>
    </div>
    <div class="delivery_section col-md-4">
      <div class="col-sm-4 col-xs-5" id="up_side">
        <section class="panel text-center m-bot-15 p-x-1-px">
          <p class="font-22">Արամ Սարգսյան</p>
          <div id="status" class="blue">
            <p class="table_number font-22 text-white p-x-1-px">2 / Դրսի սրահ</p>
          </div>
          <ul class="text-center font-22" id="hour_price">
            <li>
              <img src="assets/img/icons/clock.svg" class="height-20" alt="">
              <span id="timer">0:24:12</span>
            </li>
            <li>
              <img src="assets/img/icons/moneyGreen.svg" alt="">
              <span>8620</span>
              + <img src="assets/img/icons/precent.svg" height="20" alt=""> 10
            </li>
            <li>Զեղչ՝ 10%</li>
            <li>Կանխավճար՝ 5000</li>
            <li class="totalLine">
              <span>Ընդհանուր</span>
              <span class="totalPrice" id="totalPrice">2758</span>
            </li>
          </ul>
          <p>Հաճախորդ՝ Սոնա</p>
        </section>
        <section class="panel change-calc-panel">
          <header class="panel-heading font-20 text-center">Մանրի վերադարձման հաշվիչ</header>
          <div class="panel-body">
            <div class="form-group"><input class="form-control numberic text-center font-18" id="manr_mutq" placeholder="Վճարված գումար"></div>
            <div class="form-group"><input class="form-control text-center font-18 font-bold text-danger" id="manr_elq" readonly placeholder="Վերադարձվելիք մանրը"></div>
          </div>
        </section>
        <div class="count_of_clients m-bot15">
          <input type="number" name="clients_count" class="form-control input-sm" value="3" placeholder="Հաճախորդների քանակ" id="clients_count" autocomplete="off">
          <button class="btn btn-info btn-sm" type="button" id="commandBtn">Մուտքագրել</button>
        </div>
        <div class="form-group m-bot15">
          <input type="text" class="form-control input-sm" placeholder="Կտրոնի նկարագրություն" value="Առանց սոխի" id="checkComment">
        </div>
        <div class="m-bot15">
          <h5>Ընտրեք պատրվերի տեսակը</h5>
          <select class="form-control table-type-select" data-order_id="128">
            <option selected>Standard</option>
            <option>Բանկետ</option>
            <option>VIP</option>
          </select>
        </div>
        <div class="bg-white p-a-10-px animate_btns buttonsContainer" style="border-radius:4px" data-backend-note="keep all source-like order action buttons for backend permissions/configuration">
          <button id="addOrderBtn" type="button" class="btn btn-success btn-block m-bot-5"><a class="text-white display-block" href="rooms-add-order-item.html">Պատվեր ավելացնել</a></button>
          <button id="payPrepaymentModal" type="button" class="btn btn-primary btn-block m-bot-5" data-toggle="modal" data-target="#prepaymentModal"><a class="text-white display-block">Կանխավճար</a></button>
          <button type="button" class="btn text-white btn-block btn-warning animate_yellow" data-toggle="modal" data-target="#discountModal">Զեղչել</button>
          <button type="button" id="transformTable" class="btn text-white btn-block btn-warning animate_yellow">Տեղափոխել սեղանը</button>
          <button type="button" id="replaceItems" class="btn text-white btn-block btn-warning animate_yellow">Տեղափոխել ապրանքները</button>
          <button type="button" class="btn text-white select-client btn-block btn-primary" data-toggle="modal" data-target="#clientModal">Ընտրել հաճախորդին</button>
          <button type="button" class="btn text-white change-waiter btn-block btn-default" data-toggle="modal" data-target="#waiterPinModal">Փոխել մատուցողին</button>
          <button type="button" class="btn text-white btn-block log_admin bg-gray-dark">Ադմինիստրատիվ մուտք</button>
          <button type="button" class="btn btn-danger btn-block print_check" data-toast="Հաշիվը ուղարկվեց տպման">Տպել հաշիվ</button>
          <button type="button" class="btn btn-block btn-info close_table text-white">Հաստատել և փակել</button>
          <button type="button" class="btn btn-block btn-warning check_idram text-white animate_yellow" id="check_idram">Ստուգել <img src="assets/img/icons/idramLogo.svg" alt=""> դրամապանակը</button>
          <button type="button" class="btn btn-block btn-warning check_evoca text-white animate_yellow" id="check_evoca">Ստուգել Evoca վճարումը</button>
          <button type="button" class="btn btn-block btn-warning checkTelCell text-white animate_yellow" id="checkTelCell">Ստուգել <img src="assets/img/icons/telcellBlackIcon.svg" alt=""> դրամապանակը</button>
          <button data-toggle="modal" data-target="#zeroCloseModal" type="button" class="btn text-white btn-block btn-danger animate_red">Զրոյացնել և փակել</button>
          <button class="btn btn-default btn-block" id="addSubtable">Ավելացնել ենթասեղան</button>
          <div class="new_addon">
            <img src="assets/img/common/sale.png" alt="">
            <input class="form-control" id="instant_sale" type="password" placeholder="Զեղչի քարտ" autocomplete="new-password">
          </div>
          <div class="backButtonContainer">
            <a href="rooms-tables.html" class="backButton">
              <div class="arrowContainer"><img src="assets/img/icons/whiteArrow.svg" alt=""></div>
              <span>Վերադառնալ</span>
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>`;
}

function addOrderItemContent() {
  const groups = [
    {
      title: 'Drinks',
      icon: 'icon-glass.png',
      products: ['Օղելից խմիչք', 'Գինի Բաժակով', 'Բուսական կաթով ըմպելիքներ', 'Ֆրեշ,շեյք', 'Կոկտեյլներ', 'Շոկոլադե ըմպելիքներ', 'Գազեյրով', 'Տաք և Սառը ըմպելիքներ', 'Գինի', 'Թեյ', 'Սուրճեր', 'Ջրեղեն']
    },
    { title: 'Food', icon: 'icon-food.png', products: ['Աղցաններ', 'Նախուտեստներ', 'Տաք ուտեստներ', 'Ապուրներ', 'Պիցցա'] },
    { title: 'Kitchen', icon: 'icon-food.png', products: ['Խավարտներ', 'Սոուսներ', 'Գրիլ', 'Խմորեղեն'] },
    { title: 'Bar', icon: 'icon-beer.png', products: ['Գարեջուր', 'Օղի', 'Վիսկի', 'Կոնյակ'] },
    { title: 'Coffee', icon: 'icon-coffee.png', products: ['Սուրճ', 'Թեյ', 'Լատե', 'Կապուչինո'] }
  ];
  const products = [
    { id: 1, name: 'Սեզար աղցան', price: 2900, group: 'Lunch', image: 'order.png' },
    { id: 2, name: 'Հավի սթեյք', price: 4200, group: 'Lunch', image: 'order.png' },
    { id: 3, name: 'Պիցցա Մարգարիտա', price: 3400, group: 'Lunch', image: 'order.png' },
    { id: 4, name: 'Լատե', price: 1200, group: 'Coffee', image: 'order.png' },
    { id: 5, name: 'Կապուչինո', price: 1100, group: 'Coffee', image: 'order.png' },
    { id: 6, name: 'Ամերիկանո', price: 800, group: 'Coffee', image: 'order.png' },
    { id: 7, name: 'Խնձորի ֆրեշ', price: 900, group: 'Beverage', image: 'order.png' },
    { id: 8, name: 'Կոլա', price: 600, group: 'Beverage', image: 'order.png' },
    { id: 9, name: 'Օմլետ', price: 1800, group: 'Breakfast', image: 'order.png' },
    { id: 10, name: 'Պանրով բլիթ', price: 1500, group: 'Breakfast', image: 'order.png' }
  ];

  const menuPlaces = groups.map((group, index) => `<li title="${group.title}" class="parent_menu${index === 0 ? ' menu_item_active' : ''}" data-group="" menu_id="${index + 1}">
    <p><img src="assets/img/common/${group.icon}" alt="${group.title}"></p>
  </li>`).join('');
  const subMenus = groups.map((group, index) => `<ul class="parent_${index + 1} menuList" style="${index === 0 ? '' : 'display:none'}">
    ${group.products.map(title => `<li class="sub_menu" data-group="" data-filter="${title}">${title}</li>`).join('')}
  </ul>`).join('');

  const productCards = products.map(product => `<div class="product_item_pos" data-group="${product.group}" data-name="${product.name.toLowerCase()}">
    <div class="product_item" item_id="${product.id}" data-name="${product.name}" data-price="${product.price}" type="10">
      <div class="product_img_div"><img src="assets/img/common/${product.image}" alt="${product.name}"></div>
      <div class="product_about">
        <div class="product_name"><span class="addItemName"><span title="${product.name}">${product.name}</span></span></div>
        <div class="product_place" style="display:none">${product.group}</div>
        <div class="prodcut_price"><span>${product.price}</span> դրամ</div>
      </div>
    </div>
  </div>`).join('');

  return `<div class="add-order-page source-add-order-page">
    <div class="add-order-top col-lg-12">
      <div id="left_side" class="poqr_625_spec">
        <div class="menu poqr_625_spec">
          <button class="btn btn-block menu_item_active" id="day_menu"><span>Օրվա մենյու</span></button>
          <ul id="menulist">${menuPlaces}<div style="clear:both"></div>${subMenus}</ul>
        </div>
      </div>
      <div class="width_procent poqr_625_spec menuItemsContainer">
        <div class="menu_item_search">
          <input type="text" class="form-control" id="addItemUsingBarcode" placeholder="Ավելացնել ապրանք կշեռքի բառկոդով">
          <input type="text" class="form-control" id="menu_search" placeholder="Փնտրել ապրանք">
          <button class="btn btn-white btn-sm" type="button" id="search_mi"><i class="icon-search"></i></button>
        </div>
        <div class="shaw_top" style="display:none"></div>
        <div class="products poqr_625_spec" id="products">${productCards}</div>
        <div class="shaw_bottom" style="display:none"></div>
      </div>
      <div id="right_side" class="poqr_625_spec">
        <section class="panel text-center m-bot-15 p-x-1-px">
          <p class="font-22">Արամ Սարգսյան</p>
          <div id="status" class="blue"><p class="table_number font-22 text-white p-x-1-px">2 / Դրսի սրահ</p></div>
          <ul class="text-center font-22" id="hour_price">
            <li><img src="assets/img/icons/clock.svg" class="height-20" alt=""> <span id="timer">0:24:12</span></li>
            <li><img src="assets/img/icons/moneyGreen.svg" alt=""> <span class="add-order-total">0</span></li>
          </ul>
        </section>
        <div class="right-add-box">
          <div class="numbers_wrap">
            <div class="numbers_block"></div>
            <div class="border count-border">
              <input type="text" id="count" placeholder="Քանակը(բաժին)" value="" class="form-control numberic">
              <span class="getName" style="display:none"></span>
              <span class="isEmark" style="display:none"></span>
            </div>
            ${[1,2,3,4,5,6,7,8,9].map(n => `<div class="number_div" value="${n}">${n}</div>`).join('')}
            <div class="del_div" value="-1"><i class="icon-long-arrow-left"></i></div>
            <div class="number_div" value="0">0</div>
            <div class="number_div dot" value=".">.</div>
            <button type="button" class="add_order_item btn btn-success btn-block mec_850"><i class="icon-plus"></i> Ավելացնել</button>
            <button type="button" class="print-check-btn toDisable btn btn-danger btn-block accept mec_850" disabled><i class="icon-print"></i> Ընդունել</button>
            <div class="to_back mec_850 add-order-back"><img src="assets/img/common/order_back.png" alt=""> Վերադառնալ</div>
          </div>
        </div>
      </div>
    </div>
    <div class="list_table pop_list">
      <table id="prod_table" class="table table-striped table-hover dataTable no-footer tb table_v">
        <thead>
          <tr>
            <th>Անվանում</th>
            <th>Քանակ</th>
            <th class="hidden-phone">Գին</th>
            <th>Ընդհանուր</th>
            <th><i class="icon-edit"></i></th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>`;
}

function storeContent() {
  const stores = ['Գլխավոր պահեստ', 'Խոհանոց', 'Բար', 'Մթերք', 'Կիսաֆաբրիկատներ', 'Արտադրամաս'];
  const storeCards = stores.map((title, index) => `<div class="col-md-6 storeContainer">
    <h1 class="storeTitle">
      <a href="store-balance.html?store=${index + 1}">${title}</a>
    </h1>
    <ul class="btnsUl text-center btns_group buttonsGroup">
      <li>
        <button type="button" class="btn btn-link btn-xs btn-warning store-get-edit" value="${index + 1}" data-toggle="modal" data-target="#storeEditModal">
          <img src="assets/img/icons/pencil.svg" alt="">
        </button>
      </li>
      <li>
        <button type="button" class="btn btn-link btn-xs btn-danger" data-toggle="modal" data-target="#storeDeleteModal" style="text-decoration: none !important;">
          <img src="assets/img/icons/trash.svg" alt="">
        </button>
      </li>
    </ul>
  </div>`).join('');

  return `<div class="store-page">
    <div class="row">
      <div class="col-xs-12 clearfix">
        <button class="btn btn-success m-bot15 pull-left addNewStore" data-toggle="modal" data-target="#storeAddModal">
          <img src="assets/img/icons/plusIcon.svg" alt="">
          Ավելացնել պահեստ
        </button>
      </div>
      <div class="storesContainer">${storeCards}</div>
    </div>

    <div id="storeAddModal" class="modal fade form-horizontal addModal" role="dialog">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <h4 class="modal-title">Ավելացնել</h4>
          </div>
          <div class="modal-body">
            <form>
              <div class="form-group">
                <label class="control-label col-sm-3">Անվանում hy:</label>
                <div class="col-sm-9"><input type="text" class="required-input form-control to-send" name="title_hy"></div>
              </div>
              <div class="form-group">
                <label class="control-label col-sm-3">Անվանում en:</label>
                <div class="col-sm-9"><input type="text" class="required-input form-control to-send" name="title_en"></div>
              </div>
              <div class="form-group">
                <label class="control-label col-sm-3">Անվանում ru:</label>
                <div class="col-sm-9"><input type="text" class="required-input form-control to-send" name="title_ru"></div>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button>
            <button class="btn btn-success pull-right create-btn" data-action="createStore" type="button">Հաստատել</button>
          </div>
        </div>
      </div>
    </div>

    <div id="storeEditModal" class="modal fade form-horizontal editModal" role="dialog">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <h4 class="modal-title">Փոփոխել</h4>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label class="control-label col-sm-3">Անվանում hy:</label>
              <div class="col-sm-9"><input type="text" class="required-input form-control to-send" name="title_hy" value="Գլխավոր պահեստ"></div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-3">Անվանում en:</label>
              <div class="col-sm-9"><input type="text" class="required-input form-control to-send" name="title_en" value="Main store"></div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-3">Անվանում ru:</label>
              <div class="col-sm-9"><input type="text" class="required-input form-control to-send" name="title_ru" value="Главный склад"></div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button>
            <button class="btn btn-warning pull-right change-btn" data-action="changeStore" type="button">Հաստատել</button>
          </div>
        </div>
      </div>
    </div>

    <div id="storeDeleteModal" class="modal fade deleteModal" role="dialog">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h4 class="modal-title">Ջնջել պահեստը</h4>
          </div>
          <div class="modal-body">
            <p>Ջնջե՞լ նշված պահեստը</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button>
            <button class="btn btn-danger pull-right remove-btn" data-action="removeStore" type="button">Հաստատել</button>
          </div>
        </div>
      </div>
    </div>

    <input type="hidden" id="csrfToken" name="csrf_token" value="static-token">
  </div>`;
}

function storeBalanceContent() {
  const stores = [
    { id: 2, title: 'Զաքյան' },
    { id: 3, title: 'Արտադրամաս' },
    { id: 4, title: 'Մատակարար' },
    { id: 5, title: 'Rosemary' },
    { id: 6, title: 'Kerpak' },
  ];

  const categories = ['Բոլորը', 'Բանջարեղեն', 'Միս', 'Բար', 'Խոհանոց', 'Խմիչքներ', 'Համեմունքներ', 'Կիսաֆաբրիկատներ'];
  const balances = [
    { id: 173, storeId: 2, name: 'Կլար քառ', category: 'Կարմիրներ', unit: 'Լիտր', quantity: 35.61, cost: 900, lastCost: 900, real: 31752 },
    { id: 170, storeId: 2, name: 'Մոնին Սիրոպ', category: 'Սուրճ և թեյ', unit: 'Լիտր', quantity: 6.846, cost: 7000, lastCost: 5500, real: 38677.6 },
    { id: 559, storeId: 2, name: 'Kahlua', category: 'Գինի', unit: 'Լիտր', quantity: 1.15, cost: 13200, lastCost: 13200, real: 15180 },
    { id: 297, storeId: 2, name: 'Մետաքսե հարված President', category: 'Կարմիրներ', unit: 'Լիտր', quantity: 1.24, cost: 3400, lastCost: 3400, real: 4216 },
    { id: 610, storeId: 2, name: 'Մոնին Տրոպիկ', category: 'Սուրճ և թեյ', unit: 'Լիտր', quantity: 0.9, cost: 4708.99, lastCost: 4708.99, real: 4238.09 },
    { id: 181, storeId: 2, name: 'Baileys', category: 'Գինի', unit: 'Լիտր', quantity: 0.91, cost: 15300, lastCost: 15300, real: 13923 },
    { id: 35, storeId: 2, name: 'Կաթ', category: 'Կարմիրներ', unit: 'Լիտր', quantity: 3.9, cost: 500, lastCost: 500, real: 1950 },
    { id: 576, storeId: 2, name: 'Havana Spiced', category: 'Ջուր', unit: 'Լիտր', quantity: 0.75, cost: 13285.71, lastCost: 13714.29, real: 10264.29 },
    { id: 174, storeId: 2, name: 'Մետրաց քառ', category: 'Կարմիրներ', unit: 'Լիտր', quantity: 1.66, cost: 880, lastCost: 880, real: 1460.8 },
    { id: 345, storeId: 2, name: 'Ararat 5', category: 'Գինի', unit: 'Լիտր', quantity: 0.35, cost: 10380, lastCost: 10380, real: 3633 },
    { id: 257, storeId: 2, name: 'Գրինավելի գինի', category: 'Գինի', unit: 'Լիտր', quantity: 2.02, cost: 1140, lastCost: 1140, real: 2302.8 },
    { id: 561, storeId: 2, name: 'Բնական հյութ', category: 'Ջուր', unit: 'Լիտր', quantity: 15.75, cost: 852.63, lastCost: 757.89, real: 11779.52 },
    { id: 622, storeId: 2, name: 'Martini Rosso', category: 'Գինի', unit: 'Լիտր', quantity: 0, cost: 7800, lastCost: 7800, real: 0 },
    { id: 8745, storeId: 3, name: 'Պանիր մոցարելլա', category: 'Խոհանոց', unit: 'կգ', quantity: 0, cost: 3100, lastCost: 3150, real: 0 },
    { id: 8750, storeId: 4, name: 'Խմոր', category: 'Կիսաֆաբրիկատներ', unit: 'կգ', quantity: 112, cost: 720, lastCost: 760, real: 85120 },
  ];

  const storeTabs = stores.map((store, index) => `<li class="${index === 0 ? 'active' : ''}" data-store-tab="${store.id}">
    <a href="store-balance.html?store=${store.id}">${store.title}</a>
  </li>`).join('');

  const categoryOptions = categories.map((category, index) => `<option value="${index === 0 ? '0' : category}">${category}</option>`).join('');

  const formatStoreNumber = (value, precision = 2) => String(Number(Number(value).toFixed(precision)));

  const rows = balances.map(row => {
    const filterType = row.quantity === 0 ? 'zero' : row.quantity < 3 ? 'ending' : row.quantity > 100 ? 'excess' : 'normal';
    return `<tr class="store-balance-row" data-store="${row.storeId}" data-category="${row.category}" data-filter-type="${filterType}">
      <td>${row.id}</td>
      <td>${row.storeId}</td>
      <td><a href="item.html?id=${row.id}">${row.name}</a></td>
      <td>${row.category}</td>
      <td>${row.unit}</td>
      <td class="store-balance-quantity">${formatStoreNumber(row.quantity, 3)}</td>
      <td>${formatStoreNumber(row.cost)}</td>
      <td>${formatStoreNumber(row.lastCost)}</td>
      <td class="store-balance-real">${formatStoreNumber(row.real)}</td>
      <td>${row.quantity === 0 ? `<button class="btn btn-xs btn-danger delete-balance-row inTableIconButton" data-toggle="modal" data-target="#removeBalanceModal" data-id="${row.id}">
        <img src="assets/img/icons/trash.svg" alt="">
      </button>` : ''}</td>
    </tr>`;
  }).join('');

  const filterCells = Array.from({ length: 10 }, (_, index) => `<td>${index < 9 ? `<input class="form-control store-balance-column-search" type="text" placeholder="Փնտրել">` : ''}</td>`).join('');

  return `<div class="store-balance-page">
    <div class="row">
      <div class="panel padd15 store-balance-panel">
        <div class="grayBackground"></div>
        <header class="panel-heading">
          <ul class="nav nav-tabs menuButtonsContainer store-balance-tabs">${storeTabs}</ul>
        </header>

        <div class="store-balance-filters">
          <div class="form-group store-balance-category">
            <select class="form-control" id="materialCategoryFilter">${categoryOptions}</select>
          </div>
        </div>
        <div class="store-balance-filter-row">
          <div class="store-balance-filter-buttons">
            <button type="button" class="btn btn-default filterMaterial" value="filterEndingMaterial">
              <i class="icon-filter"></i> Ֆիլտրել վերջացող հումքերը
            </button>
            <button type="button" class="btn btn-default filterMaterial" value="filterExcessMaterial">
              <i class="icon-filter"></i> Ֆիլտրել ավելցուկով հումքերը
            </button>
          </div>
        </div>

        <div class="store-balance-datatable-bar">
          <div class="store-balance-export-buttons">
            <button type="button" class="btn btn-warning"><i class="fa fa-print"></i> Տպել</button>
            <button type="button" class="btn btn-success"><img src="assets/img/icons/ExcelLogo.svg" alt=""> Excel</button>
          </div>
          <div class="store-balance-search">
            <label>Փնտրել <input class="form-control" type="search" id="storeBalanceSearch"></label>
          </div>
        </div>

        <div class="panel-body store-balance-table-body">
          <table class="table table-bordered storeBalanceTable">
            <thead>
              <tr>
                <th>id</th>
                <th>Պահեստ</th>
                <th>Անվանում</th>
                <th>Խումբ</th>
                <th>Միավոր</th>
                <th>Քանակ</th>
                <th>Սպառման ինքնարժեք</th>
                <th>Վերջին գնման արժեք</th>
                <th>Իրական ներկա մնացորդ</th>
                <th><i class="icon-trash"></i></th>
              </tr>
              <tr class="filters">${filterCells}</tr>
            </thead>
            <tbody>${rows}</tbody>
            <tfoot>
              <tr>
                <th></th>
                <th></th>
                <th>Ընդհանուր</th>
                <th></th>
                <th></th>
                <th class="store-balance-total-quantity"></th>
                <th></th>
                <th></th>
                <th class="store-balance-total-real"></th>
                <th></th>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>

    <div id="removeBalanceModal" class="modal fade deleteModal" role="dialog">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h4 class="modal-title">Ջնջել մնացորդը</h4>
          </div>
          <div class="modal-body">
            <p>Դուք համոզված եք</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button>
            <button class="btn btn-danger pull-right delete-balance-row-submit" data-action="removeBalanceRow" type="button">Հաստատել</button>
          </div>
        </div>
      </div>
    </div>

    <input type="hidden" value="Փնտրել" id="search">
    <input type="hidden" value="2" id="store_id">
    <input type="hidden" id="csrfToken" name="csrf_token" value="static-token">
  </div>`;
}

function storeTimelineContent() {
  const stores = [
    { id: 0, title: 'Բոլորը' },
    { id: 2, title: 'Զաքյան' },
    { id: 3, title: 'Արտադրամաս' },
    { id: 4, title: 'Մատակարար' },
    { id: 5, title: 'Rosemary' },
    { id: 6, title: 'Kerpak' },
  ];

  const rows = [
    {
      id: 173,
      name: 'Կլար քառ',
      unit: 'Լիտր',
      startQty: 28.45,
      startCost: 25320,
      buyQty: 12,
      buyCost: 10800,
      transferIn: '4 / 3600',
      transferOut: '2 / 1800',
      sellQty: 6.25,
      sellCost: 5625,
      shortageQty: 0,
      shortageCost: 0,
      overplusQty: 0,
      overplusCost: 0,
      exitQty: 1.5,
      exitCost: 1350,
      balanceQty: 34.7,
      balanceCost: 30945,
    },
    {
      id: 35,
      name: 'Կաթ',
      unit: 'Լիտր',
      startQty: 8.4,
      startCost: 4200,
      buyQty: 20,
      buyCost: 10000,
      transferIn: '0 / 0',
      transferOut: '3 / 1500',
      sellQty: 18.5,
      sellCost: 9250,
      shortageQty: 0.2,
      shortageCost: 100,
      overplusQty: 0,
      overplusCost: 0,
      exitQty: 0,
      exitCost: 0,
      balanceQty: 6.7,
      balanceCost: 3350,
    },
    {
      id: 8750,
      name: 'Խմոր',
      unit: 'Կգ',
      startQty: 94,
      startCost: 67680,
      buyQty: 35,
      buyCost: 26600,
      transferIn: '0 / 0',
      transferOut: '12 / 8640',
      sellQty: 0,
      sellCost: 0,
      shortageQty: 0,
      shortageCost: 0,
      overplusQty: 1,
      overplusCost: 760,
      exitQty: 4,
      exitCost: 2880,
      balanceQty: 114,
      balanceCost: 83520,
    },
    {
      id: 8745,
      name: 'Պանիր մոցարելլա',
      unit: 'Կգ',
      startQty: 12.7,
      startCost: 40005,
      buyQty: 8,
      buyCost: 25200,
      transferIn: '1 / 3150',
      transferOut: '0 / 0',
      sellQty: 9.4,
      sellCost: 29610,
      shortageQty: 0,
      shortageCost: 0,
      overplusQty: 0,
      overplusCost: 0,
      exitQty: 0.35,
      exitCost: 1102.5,
      balanceQty: 11.95,
      balanceCost: 37642.5,
    },
    {
      id: 181,
      name: 'Baileys',
      unit: 'Լիտր',
      startQty: 1.75,
      startCost: 26775,
      buyQty: 0,
      buyCost: 0,
      transferIn: '0 / 0',
      transferOut: '0.25 / 3825',
      sellQty: 0.45,
      sellCost: 6885,
      shortageQty: 0,
      shortageCost: 0,
      overplusQty: 0,
      overplusCost: 0,
      exitQty: 0,
      exitCost: 0,
      balanceQty: 1.05,
      balanceCost: 16065,
    },
    {
      id: 561,
      name: 'Բնական հյութ',
      unit: 'Լիտր',
      startQty: 18.25,
      startCost: 13831.49,
      buyQty: 24,
      buyCost: 18189.36,
      transferIn: '6 / 4547',
      transferOut: '0 / 0',
      sellQty: 21.5,
      sellCost: 16294.62,
      shortageQty: 0,
      shortageCost: 0,
      overplusQty: 0,
      overplusCost: 0,
      exitQty: 2,
      exitCost: 1515.78,
      balanceQty: 24.75,
      balanceCost: 18757.45,
    },
  ];

  const formatNumber = (value, precision = 2) => {
    const number = Number(value || 0);
    return Number(number.toFixed(precision)).toLocaleString('en-US', { maximumFractionDigits: precision });
  };

  const tabs = stores.map((store, index) => `<li class="${index === 0 ? 'active' : ''}">
    <a href="store-timeline.html?store=${store.id}&start=2026-07-07&end=2026-07-07&clock_start=00:00:00&clock_end=23:59:59">${store.title}</a>
  </li>`).join('');

  const tableRows = rows.map(row => `<tr>
    <td><a href="item.html?id=${row.id}">${row.name}</a></td>
    <td>${row.unit}</td>
    <td>${formatNumber(row.startQty, 3)}</td>
    <td>${formatNumber(row.startCost)}</td>
    <td>${formatNumber(row.buyQty, 3)}</td>
    <td>${formatNumber(row.buyCost)}</td>
    <td>${row.transferIn}</td>
    <td>${row.transferOut}</td>
    <td>${formatNumber(row.sellQty, 3)}</td>
    <td>${formatNumber(row.sellCost, 3)}</td>
    <td>${formatNumber(row.shortageQty, 2)}</td>
    <td>${formatNumber(row.shortageCost, 2)}</td>
    <td>${formatNumber(row.overplusQty, 2)}</td>
    <td>${formatNumber(row.overplusCost, 2)}</td>
    <td>${formatNumber(row.exitQty, 3)}</td>
    <td>${formatNumber(row.exitCost, 3)}</td>
    <td>${formatNumber(row.balanceQty, 3)}</td>
    <td>${formatNumber(row.balanceCost, 3)}</td>
  </tr>`).join('');

  const total = (key) => rows.reduce((sum, row) => sum + Number(row[key] || 0), 0);
  const timelineHeaders = [
    'Անվանում',
    'Միավոր',
    'Սկզբնական քանակ',
    'Սկզբնական ինքնարժեք',
    'Մուտք Գնում Քանակ',
    'Մւտք Գնում Գին',
    'Տրանֆեր մուտք ք/ի',
    'Տրանֆեր ելք ք/ի',
    'Վաճառք Քանակ',
    'Վաճառք Ինքնարժեք',
    'Պակասորդ քանակ',
    'Պակասորդ ինքնարժեք',
    'Ավելցուկ քանակ',
    'Ավելցուկ ինքնարժեք',
    'Դուրսգրում Քանակ',
    'Դուրսգրում Ինքնարժեք',
    'Վերջնական մնացորդ Քանակ',
    'Վերջնական մնացորդ Ինքնարժեք',
  ];
  const headerCells = timelineHeaders.map(title => `<th>${title}</th>`).join('');
  const filterCells = timelineHeaders.map(() => `<th><input class="form-control store-timeline-column-search" type="text" placeholder="Փնտրել"><span class="store-timeline-sort-icon">↕</span></th>`).join('');

  return `<div class="store-timeline-page">
    <div class="row">
      <div class="panel padd15 store-timeline-panel">
        <div class="grayBackground"></div>
        <header class="panel-heading">
          <ul class="nav nav-tabs menuButtonsContainer store-timeline-tabs">${tabs}</ul>
        </header>

        <div class="col-xs-12 clearfix store-timeline-filter">
          <div class="row">
            <div class="input-group input-large padding-left0 col-sm-6 col-xs-12" data-date-format="yyyy/mm/dd" id="noPadL">
              <input type="text" class="form-control dpd1" value="2026-07-07" name="start">
              <span class="input-group-addon inputsDivider">ից</span>
              <input type="text" class="form-control dpd2" value="2026-07-07" name="end">
            </div>
            <div class="input-group input-large padding-left0 col-sm-6 col-xs-12 store-timeline-time-filter">
              <div class="input-group bootstrap-timepicker">
                <span class="input-group-btn">
                  <button class="btn btn-default button-time-icon timeIcon1" type="button"><i class="icon-time"></i></button>
                </span>
                <input type="text" class="form-control clock_start timepicker-24 tp1 border-radius0" name="clock_start" value="00:00:00">
              </div>
              <span class="input-group-addon border-radius0 inputsDivider">ից</span>
              <div class="input-group bootstrap-timepicker">
                <input type="text" class="form-control clock_end timepicker-24 tp2 border-radius0" name="clock_end" value="23:59:59">
                <span class="input-group-btn">
                  <button class="btn btn-default button-time-icon timeIcon2" type="button"><i class="icon-time"></i></button>
                </span>
              </div>
              <span class="input-group-btn">
                <button class="btn btn-md btn-info filter-btn filterButton" type="button">Ֆիլտրել</button>
              </span>
            </div>
          </div>
        </div>

        <div class="store-timeline-datatable-bar">
          <div class="store-timeline-export-buttons">
            <button type="button" class="btn btn-warning"><i class="fa fa-print"></i> Տպել</button>
            <button type="button" class="btn btn-success"><img src="assets/img/icons/ExcelLogo.svg" alt=""> Excel</button>
          </div>
          <div class="store-timeline-search">
            <label>Փնտրել <input class="form-control" type="search" id="storeTimelineSearch"></label>
          </div>
        </div>

        <div class="panel-body store-timeline-table-body">
          <div class="store-timeline-table-scroll">
            <table class="table table-bordered mytable storeTimelineTable">
              <thead>
                <tr>${headerCells}</tr>
                <tr class="store-timeline-filters">${filterCells}</tr>
              </thead>
              <tbody>${tableRows}</tbody>
              <tfoot>
                <tr>
                  <th>Ընդհանուր</th>
                  <th></th>
                  <th></th>
                  <th></th>
                  <th class="total-sum">${formatNumber(total('buyQty'), 3)}</th>
                  <th class="total-sum">${formatNumber(total('buyCost'))}</th>
                  <th></th>
                  <th></th>
                  <th class="total-sum">${formatNumber(total('sellQty'), 3)}</th>
                  <th class="total-sum">${formatNumber(total('sellCost'), 3)}</th>
                  <th class="total-sum">${formatNumber(total('shortageQty'), 2)}</th>
                  <th class="total-sum">${formatNumber(total('shortageCost'), 2)}</th>
                  <th class="total-sum">${formatNumber(total('overplusQty'), 2)}</th>
                  <th class="total-sum">${formatNumber(total('overplusCost'), 2)}</th>
                  <th class="total-sum">${formatNumber(total('exitQty'), 3)}</th>
                  <th class="total-sum">${formatNumber(total('exitCost'), 3)}</th>
                  <th class="total-sum">${formatNumber(total('balanceQty'), 3)}</th>
                  <th class="total-sum">${formatNumber(total('balanceCost'), 3)}</th>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
    </div>

    <input type="hidden" class="excelData" value="[]">
    <script>
      (function () {
        var search = document.getElementById('storeTimelineSearch');
        var table = document.querySelector('.storeTimelineTable tbody');
        if (search && table) {
          search.addEventListener('input', function () {
            var query = search.value.trim().toLowerCase();
            table.querySelectorAll('tr').forEach(function (row) {
              row.style.display = row.textContent.toLowerCase().indexOf(query) === -1 ? 'none' : '';
            });
          });
        }
        document.querySelectorAll('.store-timeline-column-search').forEach(function (input, index) {
          input.addEventListener('input', function () {
            var filters = Array.prototype.map.call(document.querySelectorAll('.store-timeline-column-search'), function (field) {
              return field.value.trim().toLowerCase();
            });
            table.querySelectorAll('tr').forEach(function (row) {
              var cells = row.children;
              var visible = filters.every(function (value, cellIndex) {
                return !value || (cells[cellIndex] && cells[cellIndex].textContent.toLowerCase().indexOf(value) !== -1);
              });
              row.style.display = visible ? '' : 'none';
            });
          });
        });

        var filter = document.querySelector('.store-timeline-page .filterButton');
        if (filter) {
          filter.addEventListener('click', function () {
            var url = new URL(window.location.href);
            var fields = ['start', 'end', 'clock_start', 'clock_end'];
            fields.forEach(function (name) {
              var input = document.querySelector('.store-timeline-page [name="' + name + '"]');
              if (input) url.searchParams.set(name, input.value);
            });
            window.history.replaceState(null, '', url.href);
          });
        }
      })();
    </script>
  </div>`;
}

function storeMaterialCategoryContent() {
  const categories = ['Բանջարեղեն', 'Միս', 'Բար', 'Խոհանոց', 'Խմիչքներ', 'Համեմունքներ'];
  const cards = categories.map((title, index) => `<div class="col-md-6 color-generator">
    <section class="panel">
      <div class="groups_items">
        <p>${title}</p>
        <div class="editAndDeleteButtons">
          <button type="button" class="btn btn-link btn-xs btn-warning store-category-edit inTableIconButton" value="${index + 1}" data-toggle="modal" data-target="#storeCategoryEditModal">
            <img src="assets/img/icons/pencil.svg" alt="">
          </button>
          <button type="button" class="btn btn-link btn-xs btn-danger inTableIconButton" data-toggle="modal" data-target="#storeCategoryDeleteModal" style="text-decoration: none !important;">
            <img src="assets/img/icons/trash.svg" alt="">
          </button>
        </div>
      </div>
    </section>
  </div>`).join('');

  const langInputs = (values = {}) => `<div class="form-group">
      <label class="control-label col-sm-3">Անվանում hy:</label>
      <div class="col-sm-9"><input type="text" class="required-input form-control to-send" name="title_hy" value="${values.hy || ''}"></div>
    </div>
    <div class="form-group">
      <label class="control-label col-sm-3">Անվանում en:</label>
      <div class="col-sm-9"><input type="text" class="required-input form-control to-send" name="title_en" value="${values.en || ''}"></div>
    </div>
    <div class="form-group">
      <label class="control-label col-sm-3">Անվանում ru:</label>
      <div class="col-sm-9"><input type="text" class="required-input form-control to-send" name="title_ru" value="${values.ru || ''}"></div>
    </div>`;

  return `<div class="store-material-category-page">
    <div class="row">
      <div class="col-xs-12 clearfix store-material-category-actions">
        <button class="btn btn-success m-bot15 pull-left addNewCategory" data-toggle="modal" data-target="#storeCategoryAddModal">
          <img src="assets/img/icons/plusIcon.svg" alt="">
          Ավելացնել խումբ
        </button>
        <form role="form" method="get" class="store-material-category-excel-form">
          <a href="#" class="btn btn-default store-material-category-excel">Excel <i class="fa fa-table"></i></a>
        </form>
      </div>
      ${cards}
    </div>

    <div id="storeCategoryAddModal" class="modal fade form-horizontal addModal" role="dialog">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <h4 class="modal-title">Ավելացնել</h4>
          </div>
          <div class="modal-body"><form>${langInputs()}</form></div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button>
            <button class="btn btn-success pull-right create-btn" data-action="createCategory" type="button">Հաստատել</button>
          </div>
        </div>
      </div>
    </div>

    <div id="storeCategoryEditModal" class="modal fade form-horizontal editModal" role="dialog">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <h4 class="modal-title">Փոփոխել</h4>
          </div>
          <div class="modal-body">${langInputs({ hy: 'Բանջարեղեն', en: 'Vegetables', ru: 'Овощи' })}</div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button>
            <button class="btn btn-warning pull-right change-btn" data-action="changeCategory" type="button">Հաստատել</button>
          </div>
        </div>
      </div>
    </div>

    <div id="storeCategoryDeleteModal" class="modal fade deleteModal" role="dialog">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h4 class="modal-title">Ջնջել խումբը</h4>
          </div>
          <div class="modal-body">
            <p>Ջնջե՞լ նշված խումբը</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button>
            <button class="btn btn-danger pull-right remove-btn" data-action="removeCategory" type="button">Հաստատել</button>
          </div>
        </div>
      </div>
    </div>

    <input type="hidden" id="csrfToken" name="csrf_token" value="static-token">
  </div>`;
}

function storeItemDetailContent() {
  const itemData = [
    {
      id: 173,
      name: 'Կլար քառ',
      code: '173',
      group: 'Կարմիրներ',
      unit: 'Լիտր',
      lastPrice: '900',
      min: '1.000',
      created: '16.06.2026 13:48',
      balances: [
        { store: 'Զաքյան', quantity: '35.610', cost: '31 752' },
        { store: 'Արտադրամաս', quantity: '0.000', cost: '0' },
        { store: 'Մատակարար', quantity: '0.000', cost: '0' },
      ],
      history: [
        { date: '16.06.2026 13:48', doc: 'Մուտք #135583', store: 'Զաքյան', type: 'Մուտք', input: '40.000', output: '', balance: '40.000', price: '900' },
        { date: '22.06.2026 19:10', doc: 'Ծախս #135812', store: 'Զաքյան', type: 'Ելք', input: '', output: '4.390', balance: '35.610', price: '900' },
      ],
    },
    {
      id: 170,
      name: 'Մոնին Սիրոպ',
      code: '170',
      group: 'Սուրճ և թեյ',
      unit: 'Լիտր',
      lastPrice: '5500',
      min: '1.000',
      created: '10.06.2026 12:20',
      balances: [
        { store: 'Զաքյան', quantity: '6.846', cost: '38 677.6' },
        { store: 'Արտադրամաս', quantity: '0.000', cost: '0' },
      ],
      history: [
        { date: '10.06.2026 12:20', doc: 'Մուտք #135421', store: 'Զաքյան', type: 'Մուտք', input: '8.000', output: '', balance: '8.000', price: '5500' },
        { date: '25.06.2026 22:30', doc: 'Ծախս #136014', store: 'Զաքյան', type: 'Ելք', input: '', output: '1.154', balance: '6.846', price: '7000' },
      ],
    },
    {
      id: 559,
      name: 'Kahlua',
      code: '559',
      group: 'Գինի',
      unit: 'Լիտր',
      lastPrice: '13200',
      min: '0.500',
      created: '03.06.2026 16:05',
      balances: [
        { store: 'Զաքյան', quantity: '1.150', cost: '15 180' },
      ],
      history: [
        { date: '03.06.2026 16:05', doc: 'Մուտք #135190', store: 'Զաքյան', type: 'Մուտք', input: '2.000', output: '', balance: '2.000', price: '13200' },
        { date: '24.06.2026 21:40', doc: 'Ծախս #135950', store: 'Զաքյան', type: 'Ելք', input: '', output: '0.850', balance: '1.150', price: '13200' },
      ],
    },
    {
      id: 11,
      name: 'Լոլիկ',
      code: '1001',
      group: 'Բանջարեղեն',
      unit: 'կգ',
      lastPrice: '650',
      min: '3.000',
      created: '01.06.2026 09:30',
      balances: [
        { store: 'Զաքյան', quantity: '18.000', cost: '11 700' },
        { store: 'Արտադրամաս', quantity: '7.500', cost: '4 875' },
      ],
      history: [
        { date: '01.06.2026 09:30', doc: 'Մուտք #135010', store: 'Զաքյան', type: 'Մուտք', input: '30.000', output: '', balance: '30.000', price: '650' },
        { date: '26.06.2026 18:10', doc: 'Ծախս #136080', store: 'Զաքյան', type: 'Ելք', input: '', output: '12.000', balance: '18.000', price: '650' },
      ],
    },
    {
      id: 12,
      name: 'Հավի կրծքամիս',
      code: '1002',
      group: 'Միս',
      unit: 'կգ',
      lastPrice: '2200',
      min: '5.000',
      created: '01.06.2026 09:40',
      balances: [
        { store: 'Զաքյան', quantity: '12.000', cost: '26 400' },
      ],
      history: [
        { date: '01.06.2026 09:40', doc: 'Մուտք #135011', store: 'Զաքյան', type: 'Մուտք', input: '25.000', output: '', balance: '25.000', price: '2200' },
        { date: '26.06.2026 20:15', doc: 'Ծախս #136105', store: 'Զաքյան', type: 'Ելք', input: '', output: '13.000', balance: '12.000', price: '2200' },
      ],
    },
    {
      id: 13,
      name: 'Սուրճ',
      code: '1003',
      group: 'Բար',
      unit: 'կգ',
      lastPrice: '5800',
      min: '1.000',
      created: '01.06.2026 09:50',
      balances: [
        { store: 'Զաքյան', quantity: '4.200', cost: '24 360' },
      ],
      history: [
        { date: '01.06.2026 09:50', doc: 'Մուտք #135012', store: 'Զաքյան', type: 'Մուտք', input: '6.000', output: '', balance: '6.000', price: '5800' },
        { date: '26.06.2026 22:05', doc: 'Ծախս #136130', store: 'Զաքյան', type: 'Ելք', input: '', output: '1.800', balance: '4.200', price: '5800' },
      ],
    },
    {
      id: 14,
      name: 'Կոլա 0.5լ',
      code: '1004',
      group: 'Խմիչքներ',
      unit: 'հատ',
      lastPrice: '260',
      min: '24.000',
      created: '01.06.2026 10:00',
      balances: [
        { store: 'Զաքյան', quantity: '96.000', cost: '24 960' },
        { store: 'Արտադրամաս', quantity: '24.000', cost: '6 240' },
      ],
      history: [
        { date: '01.06.2026 10:00', doc: 'Մուտք #135013', store: 'Զաքյան', type: 'Մուտք', input: '180.000', output: '', balance: '180.000', price: '260' },
        { date: '26.06.2026 23:10', doc: 'Ծախս #136145', store: 'Զաքյան', type: 'Ելք', input: '', output: '84.000', balance: '96.000', price: '260' },
      ],
    },
    {
      id: 15,
      name: 'Պանիր մոցարելլա',
      code: '1005',
      group: 'Խոհանոց',
      unit: 'կգ',
      lastPrice: '3100',
      min: '2.000',
      created: '01.06.2026 10:10',
      balances: [
        { store: 'Զաքյան', quantity: '9.500', cost: '29 450' },
      ],
      history: [
        { date: '01.06.2026 10:10', doc: 'Մուտք #135014', store: 'Զաքյան', type: 'Մուտք', input: '30.000', output: '', balance: '30.000', price: '3100' },
        { date: '26.06.2026 20:45', doc: 'Ծախս #136160', store: 'Զաքյան', type: 'Ելք', input: '', output: '20.500', balance: '9.500', price: '3100' },
      ],
    },
  ];

  const fallback = itemData[0];
  const dataJson = JSON.stringify(itemData).replace(/</g, '\\u003c');

  return `<div class="store-item-detail-page store-page-design" data-store-item-page>
    <script type="application/json" id="storeItemPageData">${dataJson}</script>
    <section class="panel">
      <div class="panel-body">
        <div class="row invoice-list">
          <div class="col-lg-4 col-sm-4">
            <div class="information_title">
              <img src="assets/main/image/information-icon.svg" alt="">
              <h4>Տեղեկություններ</h4>
            </div>
            <div class="information_body">
              <div><p>Հումք</p><p data-item-field="name">${fallback.name}</p></div>
              <div><p>Չափման միավոր</p><p data-item-field="unit">${fallback.unit}</p></div>
              <div><p>Վերջին գնման արժեք</p><p data-item-field="lastPrice">${fallback.lastPrice}</p></div>
              <div><p>Նվազագույն մնացորդ</p><p data-item-field="min">${fallback.min}</p></div>
              <div><p>Ստեղծման ամսաթիվ</p><p data-item-field="created">${fallback.created}</p></div>
            </div>
          </div>
          <div class="col-lg-4 col-sm-4">
            <div class="stock-balances-title">
              <img src="assets/main/image/stock-balances-icon.svg" alt="">
              <h4>Պահեստային մնացորդներ</h4>
            </div>
            <div class="stock-balances-body" data-item-balances></div>
          </div>
          <div class="col-lg-4 col-sm-4">
            <div class="assortment-title">
              <img src="assets/main/image/assortment-icon.svg" alt="">
              <h4>Տեսականի</h4>
            </div>
            <div class="assortment-body">
              <div data-item-assortment></div>
              <h5 class="semiFinishedProductsTitle">Կիսապատրաստուկներ</h5>
              <div data-item-semi-finished></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <div class="clearfix">
      <div class="row">
        <div class="col-md-3 col-sm-6 col-xs-12">
          <div class="state-overview">
            <section class="panel">
              <div class="symbol green"><i class="icon-money"></i></div>
              <div class="value"><h1 data-summary-field="sellSum">0</h1><p>Ընդհանուր վաճառք(գումար)</p></div>
            </section>
          </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12">
          <div class="state-overview">
            <section class="panel">
              <div class="symbol red"><i class="icon-money"></i></div>
              <div class="value"><h1 data-summary-field="buySum">0</h1><p>Ընդհանուր ձեռքբերում(գումար)</p></div>
            </section>
          </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12">
          <div class="state-overview">
            <section class="panel">
              <div class="symbol green"><i class="icon-exchange"></i></div>
              <div class="value"><h1 data-summary-field="sellQuantity">0</h1><p>Ընդհանուր վաճառք(քանակ)</p></div>
            </section>
          </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12">
          <div class="state-overview">
            <section class="panel">
              <div class="symbol red"><i class="icon-exchange"></i></div>
              <div class="value"><h1 data-summary-field="buyQuantity">0</h1><p>Ընդհանուր ձեռքբերում(քանակ)</p></div>
            </section>
          </div>
        </div>
      </div>
    </div>

    <section class="panel store-item-history-panel">
      <div class="panel-body">
        <div class="col-xs-12 clearfix">
          <div class="row">
            <form role="form" method="get" class="form_btn_pad store-item-filter-form">
              <input class="hidden" name="id" type="number" data-item-field="id" value="${fallback.id}">
              <div class="input-group input-large padding-left0 col-sm-6 col-xs-12" id="noPadL">
                <input readonly type="text" class="form-control dpd1" value="2026-06-01" name="start">
                <span class="input-group-addon">ից</span>
                <input readonly type="text" class="form-control dpd2" value="2026-06-30" name="end">
              </div>
              <div class="input-group input-large padding-left0 col-sm-6 col-xs-12">
                <div class="input-group bootstrap-timepicker">
                  <span class="input-group-btn"><button class="btn btn-default button-time-icon" type="button"><i class="icon-time"></i></button></span>
                  <input type="text" class="form-control timepicker-24 tp1 border-radius0" name="clock_start" value="00:00:00">
                </div>
                <span class="input-group-addon border-radius0">ից</span>
                <div class="input-group bootstrap-timepicker">
                  <input type="text" class="form-control timepicker-24 tp2 border-radius0" name="clock_end" value="23:59:59">
                  <span class="input-group-btn"><button class="btn btn-default button-time-icon" type="button"><i class="icon-time"></i></button></span>
                </div>
                <span class="input-group-btn"><button class="btn btn-md btn-info" type="submit">Ֆիլտրել</button></span>
              </div>
            </form>
          </div>
        </div>

        <div class="col-xs-12 store-item-grid-actions">
          <a class="dt-button buttons-excel buttons-html5 pull-right excelButton" href="#">
            <span><button class="btn btn-primary" type="button"><img src="assets/img/icons/ExcelLogo.svg" alt=""> Excel</button></span>
          </a>
          <a class="dt-button buttons-print buttons-html5 pull-right no-margin" id="print-table" href="#">
            <span><button class="btn btn-warning" type="button">Տպել <i class="fa fa-print" aria-hidden="true"></i></button></span>
          </a>
        </div>

        <div class="grid-view store-item-grid" id="w0">
          <div class="summary">Ցուցադրվում է <b>1-2</b> արդյունքը <b data-item-history-count>2</b>-ից:</div>
          <table class="table table-striped table-bordered store-item-history-table">
            <thead>
              <tr>
                <th><a class="asc" href="#">Ամսաթիվ</a></th>
                <th><a href="#">Id</a></th>
                <th><a href="#">Պահեստ</a></th>
                <th><a href="#">Տեսակ</a></th>
                <th><a href="#">Փաստաթուղթ</a></th>
                <th><a href="#">Ֆիսկալ համար</a></th>
                <th><a href="#">Քանակ</a></th>
                <th><a href="#">Ինքնարժեք</a></th>
                <th><a href="#">Ընդհ․ ինքնարժեք</a></th>
                <th>Մնացորդ</th>
              </tr>
              <tr class="filters">
                <td><input class="form-control" type="text"></td>
                <td><input class="form-control" type="text"></td>
                <td><input class="form-control" type="text"></td>
                <td><input class="form-control" type="text"></td>
                <td><input class="form-control" type="text"></td>
                <td><input class="form-control" type="text"></td>
                <td><input class="form-control" type="text"></td>
                <td><input class="form-control" type="text"></td>
                <td><input class="form-control" type="text"></td>
                <td></td>
              </tr>
            </thead>
            <tbody data-item-history></tbody>
            <tfoot>
              <tr class="table-footer">
                <td colspan="6"></td>
                <td data-summary-field="footerQuantity"></td>
                <td></td>
                <td data-summary-field="footerCost"></td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </section>
    <input type="hidden" id="csrfToken" name="csrf_token" value="static-token">
    <script>
      (function () {
        var page = document.querySelector('[data-store-item-page]');
        var dataEl = document.getElementById('storeItemPageData');
        if (!page || !dataEl) return;
        var items = JSON.parse(dataEl.textContent || '[]');
        var params = new URLSearchParams(window.location.search);
        var id = Number(params.get('id'));
        var item = items.find(function (row) { return row.id === id; }) || items[0];
        var escapeHtml = function (value) {
          return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[ch];
          });
        };
        var toNumber = function (value) {
          return Number(String(value || '0').replace(/\\s/g, '')) || 0;
        };
        var formatNumber = function (value, precision) {
          return String(Number(Number(value).toFixed(precision)));
        };
        Object.keys(item).forEach(function (key) {
          page.querySelectorAll('[data-item-field="' + key + '"]').forEach(function (node) {
            if (node.tagName === 'INPUT') node.value = item[key];
            else node.textContent = item[key];
          });
        });
        page.querySelector('[data-item-balances]').innerHTML = item.balances.map(function (row) {
          return '<div><p>' + escapeHtml(row.store) + ':</p><p>' + escapeHtml(row.quantity) + ' ' + escapeHtml(item.unit) + '<br>(Պահեստային ինքնարժեք ' + escapeHtml(row.cost) + ')</p></div>';
        }).join('');
        page.querySelector('[data-item-assortment]').innerHTML = [
          '<div><p><a href="menu.html?id=11&item=' + escapeHtml(item.id) + '">' + escapeHtml(item.name) + ' սեթ (' + escapeHtml(item.min) + ' ' + escapeHtml(item.unit) + ')</a></p><p>Պահեստ՝ Զաքյան</p></div>',
          '<div><p><a href="menu.html?id=11&item=658">Հատուկ առաջարկ (' + escapeHtml(item.unit) + ')</a></p><p>Պահեստ՝ Rosemary</p></div>'
        ].join('');
        page.querySelector('[data-item-semi-finished]').innerHTML = '<div class="semiFinishedProducts"><p>' + escapeHtml(item.group) + '</p><p>(' + escapeHtml(item.min) + ' ' + escapeHtml(item.unit) + ')</p></div>';

        var buySum = 0;
        var sellSum = 0;
        var buyQuantity = 0;
        var sellQuantity = 0;
        var rows = item.history.map(function (row) {
          var documentId = row.doc.replace(/\\D/g, '');
          var quantity = row.input ? toNumber(row.input) : -toNumber(row.output);
          var price = toNumber(row.price);
          var total = Math.abs(quantity) * price;
          if (quantity > 0) {
            buyQuantity += quantity;
            buySum += total;
          } else {
            sellQuantity += Math.abs(quantity);
            sellSum += total;
          }
          return '<tr><td>' + escapeHtml(row.date.replace(/\\./g, '.')) + '</td><td>' + escapeHtml(documentId) + '</td><td>' + escapeHtml(row.store) + '</td><td>' + escapeHtml(row.type) + '</td><td><a target="_blank" href="store-document-submitted.html?id=' + escapeHtml(documentId) + '">Փաստաթուղթ #' + escapeHtml(documentId) + '</a></td><td></td><td>' + escapeHtml(formatNumber(quantity, 3)) + '</td><td>' + escapeHtml(formatNumber(price, 2)) + '</td><td>' + escapeHtml(formatNumber(total, 2)) + '</td><td>' + escapeHtml(row.balance) + '</td></tr>';
        }).join('');
        page.querySelector('[data-item-history]').innerHTML = rows;
        page.querySelector('[data-item-history-count]').textContent = item.history.length;
        page.querySelector('[data-summary-field="sellSum"]').textContent = formatNumber(sellSum, 2);
        page.querySelector('[data-summary-field="buySum"]').textContent = formatNumber(buySum, 2);
        page.querySelector('[data-summary-field="sellQuantity"]').textContent = formatNumber(sellQuantity, 3);
        page.querySelector('[data-summary-field="buyQuantity"]').textContent = formatNumber(buyQuantity, 3);
        page.querySelector('[data-summary-field="footerQuantity"]').textContent = formatNumber(buyQuantity - sellQuantity, 3);
        page.querySelector('[data-summary-field="footerCost"]').textContent = formatNumber(buySum + sellSum, 2);
      })();
    </script>
  </div>`;
}

function storeItemsContent() {
  const materials = [
    { title: 'Լոլիկ', code: '1001', group: 'Բանջարեղեն', unit: 'կգ', price: '650', min: '3.000', max: '45.000', type: '' },
    { title: 'Հավի կրծքամիս', code: '1002', group: 'Միս', unit: 'կգ', price: '2200', min: '5.000', max: '80.000', type: 'Բաղադրություն' },
    { title: 'Սուրճ', code: '1003', group: 'Բար', unit: 'կգ', price: '5800', min: '1.000', max: '12.000', type: '' },
    { title: 'Կոլա 0.5լ', code: '1004', group: 'Խմիչքներ', unit: 'հատ', price: '260', min: '24.000', max: '180.000', type: '' },
    { title: 'Պանիր մոցարելլա', code: '1005', group: 'Խոհանոց', unit: 'կգ', price: '3100', min: '2.000', max: '30.000', type: 'Պարունակություն' },
  ];

  const materialRows = materials.map((row, index) => `<tr>
    <td><a href="item.html?id=${index + 11}">${row.title}</a></td>
    <td>${row.code}</td>
    <td>${row.group}</td>
    <td>${row.unit}</td>
    <td>${row.price}</td>
    <td>${row.min}</td>
    <td>${row.max}</td>
    <td>
      <div class="inTableButtonsContainer">
        <button class="btn btn-warning btn-xs store-item-edit inTableIconButton" value="${index + 1}" type="button" data-toggle="modal" data-target="#storeItemEditModal">
          <img src="assets/img/icons/pencil.svg" alt="">
        </button>
        ${row.type ? `<button class="btn btn-info btn-xs open-semi-finished-modal" type="button" data-toggle="modal" data-target="#storeItemCompositionModal">${row.type}</button>` : ''}
        ${index === 1 ? `<a class="btn btn-xs btn-success excelInTable inTableIconButton" href="#">
          <img src="assets/img/icons/ExcelLogo.svg" alt="">
        </a>` : ''}
      </div>
    </td>
  </tr>`).join('');

  const modalLangInputs = (values = {}) => `<div class="form-group">
      <label class="control-label col-sm-3">Անվանում Հայերեն :</label>
      <div class="col-sm-9"><input type="text" class="required-input form-control to-send material_title" name="title_hy" value="${values.hy || ''}"></div>
    </div>
    <div class="form-group">
      <label class="control-label col-sm-3">Անվանում English :</label>
      <div class="col-sm-9"><input type="text" class="required-input form-control to-send material_title" name="title_en" value="${values.en || ''}"></div>
    </div>
    <div class="form-group">
      <label class="control-label col-sm-3">Անվանում Русский :</label>
      <div class="col-sm-9"><input type="text" class="required-input form-control to-send material_title" name="title_ru" value="${values.ru || ''}"></div>
    </div>`;

  const materialContentInputs = (buttonClass = 'add-material-content', values = {}) => `<div class="form-group tableInputs">
    <div class="col-md-3">
      <label>Միավոր</label>
      <select name="unit_id" class="form-control">
        <option${values.unit === 'կգ' ? ' selected' : ''}>կգ</option>
        <option>գր</option>
        <option>լ</option>
        <option>հատ</option>
      </select>
    </div>
    <div class="col-md-3">
      <label>Գինը</label>
      <input type="number" name="price" class="form-control non-negative-input" value="${values.price || ''}">
    </div>
    <div class="col-md-3">
      <label>Նվազ․մնացորդ</label>
      <input type="number" name="min_quantity" class="form-control non-negative-input" value="${values.min || ''}">
    </div>
    <div class="col-md-3">
      <label>Առավ․մնացորդ</label>
      <input type="number" name="max_quantity" class="form-control non-negative-input" value="${values.max || ''}">
    </div>
    <div class="col-md-3">
      <label>Կոդ</label>
      <input type="text" name="code" class="form-control non-negative-input" value="${values.code || ''}">
    </div>
    <div class="col-md-3">
      <button type="button" class="btn btn-success ${buttonClass}">ավելացնել</button>
    </div>
  </div>`;

  const contentTable = (withRow = false) => `<table class="table table-bordered store-item-content-table">
    <thead>
      <tr>
        <th>Միավոր</th>
        <th>Գինը</th>
        <th>Նվազ. մնացորդ</th>
        <th>Առավ.մնացորդ</th>
        <th>Կոդ</th>
        <th><i class="icon-cogs"></i></th>
      </tr>
    </thead>
    <tbody>
      ${withRow ? `<tr>
        <td>կգ</td>
        <td>650</td>
        <td>3</td>
        <td>45</td>
        <td>1001</td>
        <td><button class="btn btn-danger btn-xs" type="button"><i class="icon-trash"></i></button></td>
      </tr>` : ''}
    </tbody>
  </table>`;

  const materialModalBody = (values = {}, buttonClass) => `${modalLangInputs(values)}
    <div class="form-group">
      <label class="control-label col-sm-3">Խումբ:</label>
      <div class="col-sm-9">
        <select name="material_category_id" class="form-control to-send">
          <option value="0"${values.category ? '' : ' selected'}>- -</option>
          <option${values.category === 'Բանջարեղեն' ? ' selected' : ''}>Բանջարեղեն</option>
          <option>Միս</option>
          <option>Բար</option>
          <option>Խոհանոց</option>
          <option>Խմիչքներ</option>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label class="control-label col-sm-3">Հումքի տեսակ:</label>
      <div class="col-sm-9">
        <select name="semi_finished" class="form-control to-send">
          <option>Հումք</option>
          <option>Կիսապատրաստուկ</option>
          <option>Խումբ</option>
        </select>
      </div>
    </div>
    <div class="form-group store-item-switch-row">
      <div class="m-bot15">
        <label class="col-lg-3 control-label">Օգտակար քաշի հաշվարկ</label>
        <div class="col-lg-9">
          <div class="switch switch-square" data-on-label="Այո" data-off-label="Ոչ">
            <input type="checkbox" class="to-send switcher-item" name="useful_weight" value="1">
          </div>
        </div>
      </div>
    </div>
    ${materialContentInputs(buttonClass, values.content || {})}
    ${contentTable(Boolean(values.contentRow))}`;

  return `<div class="store-items-page">
    <div class="row store_padding">
      <div class="col-xs-12 clearfix store-items-top-actions">
        <button class="btn btn-success m-bot15 pull-left addNewMaterial" data-toggle="modal" data-target="#storeItemAddModal">
          <img src="assets/img/icons/plusIcon.svg" alt="">
          Ավելացնել հումք
        </button>
      </div>
      <div class="store-items-not-used">
        <input type="checkbox" id="notInItems">
        <label for="notInItems">Հումքեր որոնք չեն օգտագործվում ապրանքներում</label>
      </div>
      <div class="panel store-items-panel">
        <div class="panel-body">
          <a class="dt-button buttons-excel buttons-html5 pull-right excelButton" tabindex="0">
            <span>
              <button class="btn btn-primary" type="button">
                <img src="assets/img/icons/ExcelLogo.svg" alt="">
                Excel
              </button>
            </span>
          </a>
          <div class="store-items-page-size">
            <select class="form-control input-sm" name="StoreItemsSearchForm[pageSize]">
              <option selected>10</option>
              <option>30</option>
              <option>50</option>
              <option>100</option>
              <option>500</option>
              <option>1000</option>
            </select>
            <span class="store-items-page-summary">Ցուցադրված են <b>1-ից 10-ը</b> ընդհանուր <b>646-ից</b>:</span>
          </div>
          <div class="grid-view">
            <table class="table table-striped table-advance table-bordered table-hover mytable store-items-table" id="storeItemsTable">
              <thead>
                <tr style="background:white;">
                  <th><a class="asc" href="#">Անվանում</a></th>
                  <th>Կոդ</th>
                  <th>Խումբ</th>
                  <th>Չափմ․ միավ․</th>
                  <th>Գնման արժեք</th>
                  <th>Նվազ․ քանակ</th>
                  <th>Առավ․ քանակ</th>
                  <th><i class="icon-cogs"></i></th>
                </tr>
                <tr class="filters" style="background:white;">
                  <td><input class="form-control" type="text" value=""></td>
                  <td><input class="form-control" type="text" value=""></td>
                  <td><input class="form-control" type="text" value=""></td>
                  <td><input class="form-control" type="text" value=""></td>
                  <td><input class="form-control" type="text" value=""></td>
                  <td><input class="form-control" type="text" value=""></td>
                  <td><input class="form-control" type="text" value=""></td>
                  <td>&nbsp;</td>
                </tr>
              </thead>
              <tbody>${materialRows}</tbody>
            </table>
            <div class="row store-items-grid-footer">
              <div class="col-sm-5"><div class="summary">Ցուցադրված է 1-ից 5-ը 5 գրառումից</div></div>
              <div class="col-sm-7">
                <div class="dataTables_paginate paging_bootstrap pagination">
                  <ul>
                    <li class="prev disabled"><a href="#">← Նախորդ</a></li>
                    <li class="active"><a href="#">1</a></li>
                    <li class="next disabled"><a href="#">Հաջորդ →</a></li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div id="storeItemAddModal" class="modal fade form-horizontal addModal" role="dialog">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <h4 class="modal-title">Ավելացնել հումք</h4>
          </div>
          <div class="modal-body">${materialModalBody({}, 'add-material-content')}</div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button>
            <button class="btn btn-success pull-right create-material-btn" type="button">Հաստատել</button>
          </div>
        </div>
      </div>
    </div>

    <div id="storeItemEditModal" class="modal fade form-horizontal editModal" role="dialog">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <h4 class="modal-title">Փոփոխել հումքը</h4>
          </div>
          <div class="modal-body">${materialModalBody({
            hy: 'Լոլիկ',
            en: 'Tomato',
            ru: 'Помидор',
            category: 'Բանջարեղեն',
            content: { unit: 'կգ', price: '650', min: '3', max: '45', code: '1001' },
            contentRow: true
          }, 'change-material-content')}</div>
          <input type="hidden" class="removed-content" value="[]">
          <div class="modal-footer">
            <button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button>
            <button class="btn btn-warning pull-right change-material-btn" type="button">Հաստատել</button>
          </div>
        </div>
      </div>
    </div>

    <div id="storeItemCompositionModal" class="modal fade" role="dialog">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header"><h4 class="modal-title">Բաղադրություն</h4></div>
          <div class="modal-body">
            <div class="finalMassContainer">
              <label for="finalMassOfItem">Կիսապատրաստուկի վերջնական զանգված</label>
              <div class="inputContainer"><input type="number" id="finalMassOfItem" placeholder="1"><span class="measureUnit">կգ</span></div>
            </div>
            <div class="form-inline prepack_content m-bot15">
              <select class="form-control select2" name="item"><option>--Հումք--</option><option>Լոլիկ կգ</option></select>
              <select class="form-control select2" name="store"><option>--Պահեստ--</option><option>Գլխավոր պահեստ</option></select>
              <input id="count" class="form-control non-negative-input" type="number" min="0" name="count" placeholder="Քանակ">
              <button class="btn btn-sm btn-success add-semi-finished-content" type="button"><i class="icon-plus"></i></button>
            </div>
            <table class="table table-bordered store-item-composition-table">
              <thead><tr><th>Հումք</th><th>Պահեստ</th><th>Նշված Քանակ</th><th>Քանակ</th><th>Ինքնարժեք</th><th><i class="icon-trash"></i></th></tr></thead>
              <tbody><tr><td>Լոլիկ</td><td>Գլխավոր պահեստ</td><td>1</td><td>1.000</td><td>650</td><td><button class="btn btn-danger btn-xs" type="button"><i class="icon-trash"></i></button></td></tr></tbody>
            </table>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button>
          </div>
        </div>
      </div>
    </div>

    <input type="hidden" id="isStoreItems" value="true">
    <input type="hidden" id="csrfToken" name="csrf_token" value="static-token">
  </div>`;
}

const storeDocumentTemplateByType = {
  'Մուտք': 'entry',
  'Ելք': 'exit',
  'Վաճառք': 'sell',
  'Վերադարձ': 'return',
  'Տրանսֆեր': 'transfer',
  'Կիսապատրաստուկ': 'semiFinished',
  'Վերահաշվարկ': 'recalculation',
  'Հետին ամսաթվով վերահաշվարկ': 'backDatedRecalculation',
  'Արտադրություն': 'entry',
  'Մատակարարից մուտք': 'buy'
};

function storeDocumentRows() {
  return [
    {
      id: 135583,
      date: '2026-06-16 13:48',
      type: 'Հետին ամսաթվով վերահաշվարկ',
      company: '',
      status: 'հաստատված',
      description: 'Բար Մայիս 2026 շարժունակություն',
      identification: '',
      amount: '-2969.18',
      check: '',
      state: 'danger',
      editable: false,
      submitted: true
    },
    {
      id: 135433,
      date: '2026-06-15 15:19',
      type: 'Տրանսֆեր',
      company: '',
      status: 'նոր',
      description: '15.06 արտադրամասից վերապակ',
      identification: '',
      amount: '0',
      check: '',
      state: 'success',
      editable: true,
      submitted: false
    },
    {
      id: 135432,
      date: '2026-06-15 15:18',
      type: 'Կիսապատրաստուկ',
      company: '',
      status: 'հաստատված',
      description: '15.06 արտադրամասից գարեջան',
      identification: '',
      amount: '0',
      check: '',
      state: 'danger',
      editable: false,
      submitted: true
    },
    {
      id: 135431,
      date: '2026-06-15 15:15',
      type: 'Վերահաշվարկ',
      company: '',
      status: 'հաստատված',
      description: '15.11Մատակարարից գարեջան',
      identification: '',
      amount: '0',
      check: '',
      state: 'danger',
      editable: false,
      submitted: true
    },
    {
      id: 135429,
      date: '2026-06-15 15:13',
      type: 'Մատակարարից մուտք',
      company: '',
      status: 'հաստատված',
      description: '15.06 Մատակարարից գարեջան',
      identification: '',
      amount: '0',
      check: '',
      state: 'danger',
      editable: false,
      submitted: true
    },
    {
      id: 135427,
      date: '2026-06-15 15:11',
      type: 'Տրանսֆեր',
      company: '',
      status: 'հաստատված',
      description: '14.06 արտադրամասից գարեջան',
      identification: '',
      amount: '0',
      check: '',
      state: 'danger',
      editable: false,
      submitted: true
    },
    {
      id: 135426,
      date: '2026-06-15 15:09',
      type: 'Տրանսֆեր',
      company: '',
      status: 'ընթացքի մեջ',
      description: '14.06 գարեջանից վերապակ',
      identification: '',
      amount: '0',
      check: '',
      state: 'success',
      editable: true,
      submitted: false
    },
    {
      id: 135425,
      date: '2026-06-15 15:06',
      type: 'Տրանսֆեր',
      company: '',
      status: 'հաստատված',
      description: '14.06 Մատակարարից գարեջան',
      identification: '',
      amount: '0',
      check: '',
      state: 'danger',
      editable: false,
      submitted: true
    },
    {
      id: 135424,
      date: '2026-06-15 15:05',
      type: 'Տրանսֆեր',
      company: '',
      status: 'հաստատված',
      description: '13.06 Մատակարարից արտադրամաս',
      identification: '',
      amount: '0',
      check: '',
      state: 'danger',
      editable: false,
      submitted: true
    },
    {
      id: 135422,
      date: '2026-06-15 15:03',
      type: 'Տրանսֆեր',
      company: '',
      status: 'հաստատված',
      description: '13.06 Մատակարարից գարեջան',
      identification: '',
      amount: '0',
      check: '',
      state: 'danger',
      editable: false,
      submitted: true
    },
    {
      id: 2148,
      date: '2026-07-06 11:35',
      type: 'Մուտք',
      company: 'Գլխավոր պահեստ',
      status: 'Նոր',
      description: 'Բանջարեղենի մուտք',
      identification: 'IN-10045',
      amount: '0',
      check: '',
      state: 'success',
      editable: true,
      submitted: false
    },
    {
      id: 2147,
      date: '2026-07-06 10:20',
      type: 'Ելք',
      company: 'Խոհանոց',
      status: 'Հաստատված',
      description: 'Օրվա ծախս',
      identification: 'OUT-204',
      amount: '18,450',
      check: '#1028',
      state: 'success',
      editable: true,
      submitted: false
    },
    {
      id: 2146,
      date: '2026-07-05 18:10',
      type: 'Վաճառք',
      company: 'Բար',
      status: 'Կատարված',
      description: 'Ավտոմատ փաստաթուղթ',
      identification: 'SALE-93',
      amount: '32,100',
      check: '#1024',
      state: 'danger',
      editable: false,
      submitted: true
    },
    {
      id: 2145,
      date: '2026-07-05 14:42',
      type: 'Վերադարձ',
      company: 'Մթերք',
      status: 'Մշակվում է',
      description: 'Վերադարձ մատակարարին',
      identification: 'RET-18',
      amount: '7,800',
      check: '',
      state: 'success',
      editable: true,
      submitted: false
    },
    {
      id: 133750,
      date: '2026-06-07 09:01',
      type: 'Կիսապատրաստուկ',
      company: '',
      status: 'հաստատված',
      description: 'Խոհանոց Մայիս շարժունակություն',
      identification: '',
      amount: '-0',
      check: '',
      state: 'danger',
      editable: false,
      submitted: true
    },
    {
      id: 133696,
      date: '2026-06-06 17:49',
      type: 'Վերահաշվարկ',
      company: '',
      status: 'հաստատված',
      description: 'Մատակարար մայիս 2026',
      identification: '',
      amount: '-1770',
      check: '',
      state: 'danger',
      editable: false,
      submitted: true
    },
    {
      id: 133639,
      date: '2026-06-06 15:24',
      type: 'Տրանսֆեր',
      company: '',
      status: 'հաստատված',
      description: '30.05 Մատակարարից գարեջան',
      identification: '',
      amount: '0',
      check: '',
      state: 'danger',
      editable: false,
      submitted: true
    },
    {
      id: 133622,
      date: '2026-06-06 14:40',
      type: 'Տրանսֆեր',
      company: '',
      status: 'հաստատված',
      description: '23.05 Մատակարարից գարեջան',
      identification: '',
      amount: '0',
      check: '',
      state: 'danger',
      editable: false,
      submitted: true
    },
    {
      id: 133576,
      date: '2026-06-06 12:04',
      type: 'Տրանսֆեր',
      company: '',
      status: 'հաստատված',
      description: '6.06 գարեջանից մատակարար',
      identification: '',
      amount: '0',
      check: '',
      state: 'danger',
      editable: false,
      submitted: true
    }
  ].map(doc => ({
    ...doc,
    template: storeDocumentTemplateByType[doc.type] || 'entry'
  }));
}

function storeDocumentDetailUrl(doc) {
  const page = doc.submitted ? 'store-document-submitted.html' : 'store-document-content.html';
  return `${page}?id=${doc.id}&template=${doc.template}`;
}

function storeDocumentsContent() {
  const documents = storeDocumentRows();

  const rows = documents.map(doc => `<tr class="${doc.state}">
    <td>${doc.id}</td>
    <td>${doc.date}</td>
    <td>${doc.type}</td>
    <td>${doc.company}</td>
    <td>${doc.status}</td>
    <td>${doc.description}</td>
    <td>${doc.identification}</td>
    <td>${doc.amount}</td>
    <td>${doc.check ? `<a href="reports-check.html?id=${doc.check.replace('#', '')}">${doc.check}</a>` : ''}</td>
    <td>
      <div class="inTableButtonsContainer">
        <a class="btn btn-info btn-xs" href="${storeDocumentDetailUrl(doc)}">Մանրամասն</a>
        ${doc.editable ? `<button class="btn btn-danger btn-xs inTableIconButton" data-toggle="modal" data-target="#storeDocumentDeleteModal">
          <img src="assets/img/icons/trash.svg" alt="">
        </button>
        <button class="btn btn-warning btn-xs store-document-edit inTableIconButton" value="${doc.id}" data-toggle="modal" data-target="#storeDocumentEditModal">
          <img src="assets/img/icons/pencil.svg" alt="">
        </button>` : ''}
      </div>
    </td>
  </tr>`).join('');

  const filterCells = ['Փնտրել', 'Փնտրել', 'Փնտրել', 'Փնտրել', 'Փնտրել', 'Փնտրել', 'Փնտրել', 'Փնտրել', 'Փնտրել', 'Փնտրել']
    .map((placeholder, index) => `<td><div class="store-documents-column-filter"><input class="form-control" type="text" placeholder="${placeholder}">${index < 9 ? '<span class="sort-arrows">↕</span>' : ''}</div></td>`)
    .join('');

  const documentTypeOptions = `<option>Մուտք</option>
    <option>Ելք</option>
    <option>Վաճառք</option>
    <option>Վերադարձ</option>
    <option>Տրանսֆեր</option>
    <option>Կիսապատրաստուկ</option>
    <option>Վերահաշվարկ</option>
    <option>Հետին ամսաթվով վերահաշվարկ</option>
    <option>Արտադրություն</option>
    <option>Մատակարարից մուտք</option>`;

  const documentForm = (values = {}) => `<form>
    <div class="form-group">
      <label class="control-label col-sm-3">${values.typeLabel || 'Document type'} :</label>
      <div class="col-sm-9">
        <select name="type_id" class="form-control to-send">${documentTypeOptions}</select>
      </div>
    </div>
    <div class="form-group">
      <label class="control-label col-sm-3">Identification number:</label>
      <div class="col-sm-9">
        <input type="text" class="form-control non-negative-input to-send" name="identification_number" value="${values.identification || ''}">
      </div>
    </div>
    <div class="form-group">
      <label class="control-label col-sm-3">Նկարագրություն :</label>
      <div class="col-sm-9">
        <input type="text" class="form-control to-send" name="description" value="${values.description || ''}">
      </div>
    </div>
  </form>`;

  return `<div class="store-documents-page">
    <div class="row">
      <div class="col-xs-12 clearfix store-documents-header">
        <button class="btn btn-success m-bot15 pull-left addNewDocument" data-toggle="modal" data-target="#storeDocumentAddModal">
          <img src="assets/img/icons/plusIcon.svg" alt="">
          Ստեղծել փաստաթուղթ
        </button>
        <form role="form" method="get" class="form_btn_pad store-documents-date-form">
          <div class="input-group input-large padding-left0 col-sm-6 col-xs-12 pull-right header_filter" data-date-format="yyyy/mm/dd" id="noPadL">
            <input type="text" class="form-control dpd1" value="2026-01-01" name="start_date">
            <span class="input-group-addon inputsDivider">ից</span>
            <input type="text" class="form-control dpd2" value="2026-07-06" name="end_date">
            <span class="input-group-btn">
              <button class="btn btn-md btn-info padding5" type="submit">Ֆիլտրել</button>
            </span>
          </div>
        </form>
      </div>

      <div class="panel store-documents-panel">
        <div class="panel-body padd15">
          <button class="btn btn-default filter_btn store-documents-type-filter" data-toggle="modal" data-target="#storeDocumentFilterModal">Ֆիլտրել ըստ փասթաթխտի տեսակի</button>
          <div class="store-documents-datatable-bar">
            <div class="store-documents-export-buttons">
              <button type="button" class="btn btn-warning"><i class="fa fa-print"></i> Տպել</button>
              <button type="button" class="btn btn-success"><img src="assets/img/icons/ExcelLogo.svg" alt=""> Excel</button>
            </div>
            <div class="store-documents-search">
              <label>Փնտրել <input class="form-control" type="search"></label>
            </div>
          </div>
          <table class="table table-bordered mytable store-documents-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Ամսաթիվ</th>
                <th>Փաստաթղթի<br>Տեսակ</th>
                <th>Ընկերություն</th>
                <th>Կարգավիճակ</th>
                <th>Նկարագրություն</th>
                <th>Նույնականացման համար</th>
                <th>Գումար</th>
                <th>Կտրոն</th>
                <th><i class="icon-cogs"></i></th>
              </tr>
              <tr class="filters">${filterCells}</tr>
            </thead>
            <tbody>${rows}</tbody>
          </table>
          <div class="row store-documents-grid-footer">
            <div class="col-sm-5">
              <div class="dataTables_info">Ցուցադրված է 1-ից 50-ը 683 տողից</div>
            </div>
            <div class="col-sm-7">
              <div class="dataTables_paginate paging_bootstrap pagination">
                <ul>
                  <li class="prev disabled"><a href="#">Նախորդ</a></li>
                  <li class="active"><a href="#">1</a></li>
                  <li><a href="#">2</a></li>
                  <li><a href="#">3</a></li>
                  <li><a href="#">4</a></li>
                  <li><a href="#">5</a></li>
                  <li><a href="#">...</a></li>
                  <li><a href="#">14</a></li>
                  <li class="next"><a href="#">Հաջորդ</a></li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div id="storeDocumentAddModal" class="modal fade form-horizontal addModal" role="dialog">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <h4 class="modal-title">Ստեղծել փաստաթուղթ</h4>
          </div>
          <div class="modal-body">${documentForm()}</div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button>
            <button class="btn btn-success pull-right create-btn" data-action="createDocument" type="button">Հաստատել</button>
          </div>
        </div>
      </div>
    </div>

    <div id="storeDocumentEditModal" class="modal fade form-horizontal editModal" role="dialog">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <h4 class="modal-title">Փոփոխել փաստաթուղթը</h4>
          </div>
          <div class="modal-body">
            <input type="text" class="hidden" id="newDocumentType" value="1">
            ${documentForm({ typeLabel: 'Տեսակ', identification: 'IN-10045', description: 'Բանջարեղենի մուտք' })}
          </div>
          <input type="hidden" id="editRowId" value="2148">
          <div class="modal-footer">
            <button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button>
            <button class="btn btn-warning pull-right change-btn" data-action="changeDocument" type="button">Հաստատել</button>
          </div>
        </div>
      </div>
    </div>

    <div id="storeDocumentDeleteModal" class="modal fade deleteModal" role="dialog">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h4 class="modal-title">Ջնջել փաստաթուղթը</h4>
          </div>
          <div class="modal-body"><p>Ջնջե՞լ նշված փաստաթուղթը</p></div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button>
            <button class="btn btn-danger pull-right remove-btn" data-action="removeDocument" type="button">Հաստատել</button>
          </div>
        </div>
      </div>
    </div>

    <div id="storeDocumentFilterModal" class="modal fade deleteModal" role="dialog">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h4 class="modal-title">Ֆիլտրել փաստաթուղթը</h4>
          </div>
          <div class="modal-body store-documents-filter-body">
            <input type="checkbox" id="storeDocumentCheckAll" name="documentTypes" checked>
            <label for="storeDocumentCheckAll" class="store-documents-check-all"><strong>Բոլորը</strong></label>
            ${['Մուտք', 'Ելք', 'Վաճառք', 'Վերադարձ', 'Տրանսֆեր', 'Կիսապատրաստուկ', 'Վերահաշվարկ', 'Հետին ամսաթվով վերահաշվարկ', 'Արտադրություն', 'Մատակարարից մուտք'].map((type, index) => `<div>
              <input type="checkbox" class="filter_checkbox" name="filter_checkbox" data-id="${index + 1}" id="storeDocumentType${index + 1}" checked>
              <label for="storeDocumentType${index + 1}">${type}</label>
            </div>`).join('')}
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button>
            <button class="btn btn-primary pull-right filter_submit" type="button">Հաստատել</button>
          </div>
        </div>
      </div>
    </div>

    <input type="hidden" id="csrfToken" name="csrf_token" value="static-token">
  </div>`;
}

function storeDocumentDetailContent(mode) {
  const documents = storeDocumentRows();
  const defaultDoc = documents.find(doc => (mode === 'submitted') === doc.submitted) || documents[0];
  const data = JSON.stringify(documents).replace(/</g, '\\u003c');

  return `<div class="store-document-detail-page" data-detail-mode="${mode}" data-default-id="${defaultDoc.id}">
    <div class="row">
      <div class="col-xs-12">
        <section class="panel">
          <div class="panel-body">
            <div class="row invoice-list store-document-detail-head">
              <div class="col-lg-4 col-sm-5">
                <h4>Տեղեկություններ</h4>
                <p>
                  Փաստաթուղթ: <strong>#<span data-doc-field="id">${defaultDoc.id}</span></strong><br>
                  Ամսաթիվ: <span data-doc-field="date">${defaultDoc.date}</span><br>
                  Տեսակը: <span data-doc-field="type">${defaultDoc.type}</span><br>
                  Ընկերություն: <span data-doc-field="company">${defaultDoc.company || '-'}</span><br>
                  Նկարագրություն: <span data-doc-field="description">${defaultDoc.description}</span><br>
                  Նույնականացման համար: <span data-doc-field="identification">${defaultDoc.identification || '-'}</span>
                </p>
              </div>
              <div class="col-lg-4 col-sm-4">
                <h4>Կարգավիճակ</h4>
                <p>
                  <span class="label label-info" data-doc-field="status">${defaultDoc.status}</span><br>
                  Front-end template: <code data-doc-field="template">${defaultDoc.template}</code><br>
                  Էջ: <code>${mode === 'submitted' ? 'store_document_submitted.php' : 'store_document_content.php'}</code>
                </p>
              </div>
              <div class="col-lg-4 col-sm-3 text-right store-document-detail-actions">
                <a class="btn btn-default" href="store-documents.html"><i class="fa fa-arrow-left"></i> Վերադառնալ</a>
                <button class="btn btn-warning" type="button"><i class="fa fa-print"></i> Տպել</button>
                <button class="btn btn-success" type="button"><img src="assets/img/icons/ExcelLogo.svg" alt=""> Excel</button>
              </div>
            </div>
          </div>
        </section>

        <div class="col-xs-12 clearfix store-document-work-actions" data-edit-only>
          <button class="btn btn-success m-bot15 pull-left" data-toggle="modal" data-target="#addModal">
            <img src="assets/img/icons/plusIcon.svg" alt=""> Ավելացնել հումք
          </button>
          <button class="btn btn-primary m-bot15 pull-left" type="button" style="margin-left:10px">Approve</button>
          <button class="btn btn-success m-bot15 pull-left" type="button" style="margin-left:10px">Ձևակերպել</button>
        </div>

        <section class="panel">
          <header class="panel-heading">
            <span data-doc-table-title>${mode === 'submitted' ? 'Ձևակերպված փաստաթուղթ' : 'Փաստաթղթի կազմ'}</span>
          </header>
          <div class="panel-body store-document-detail-table-wrap">
            <table class="table table-bordered mytable store-document-detail-table" id="docContTable">
              <thead data-doc-table-head></thead>
              <tbody data-doc-table-body></tbody>
              <tfoot data-doc-table-foot></tfoot>
            </table>
          </div>
        </section>

        <div class="alert alert-info store-document-template-note">
          Այս static mock-ը կրկնում է source-ի սկզբունքը. URL-ը նույնն է, բայց փաստաթղթի տեսակից կախված բացվում է համապատասխան front-end template (<code>buy</code>, <code>transfer</code>, <code>semiFinished</code>, <code>recalculation</code>, <code>backDatedRecalculation</code> և այլն):
        </div>
      </div>
    </div>
    <script type="application/json" id="storeDocumentDetailData">${data}</script>
  </div>`;
}

function reportsContent() {
  const list = rows => `<ul class="nav nav-pills nav-stacked reportsList">${rows.map(row => `<li><a>${row.title}<span class="label ${row.label} pull-right r-activity">${row.value}</span></a></li>`).join('')}</ul>`;

  const cards = [
    {
      title: 'Սրահներ/սեղաններ',
      asideClass: 'green-border',
      rows: [
        { title: 'Ընդհանուր հաշիվներ', value: '186000', label: 'label-info' },
        { title: 'Վճարված', value: '174000', label: 'label-warning' },
        { title: 'Պարտք', value: '12000', label: 'label-danger' },
        { title: 'Զեղչ', value: '8500', label: 'label-success' },
        { title: 'Միջին հաշիվ', value: '20666', label: 'label-primary' },
        { title: 'Շահույթ', value: '108500', label: 'label-success' },
        { title: 'Ըստ սեղանների', value: '4.65', label: 'label-warning' },
        { title: 'Ըստ հաճ. քանակի', value: '2.35', label: 'label-danger' },
        { title: 'Ըստ թեյավճարի', value: '920 (4.8%)', label: 'label-success' },
        { title: 'Սպասարկված հաճախորդներ', value: '79', label: 'label-success' }
      ]
    },
    {
      title: 'Տեսականի',
      asideClass: 'r_border',
      rows: [
        { title: 'Վաճառված ապրանքների քանակ', value: '236', label: 'label-info' },
        { title: 'Ընդհանուր ինքնարժեք', value: '69000', label: 'label-danger' },
        { title: 'Ընդհանուր վաճառք', value: '154000', label: 'label-warning' }
      ]
    },
    {
      title: 'Չեկեր',
      asideClass: 'b_border',
      rows: [
        { title: 'Չեկերի քանակ', value: '42', label: 'label-success' },
        { title: 'Զրոյացված', value: '3', label: 'label-danger' },
        { title: 'Ջնջված ապրանքով', value: '5', label: 'label-warning' },
        { title: 'Դատարկ սեղաններ', value: '2', label: 'label-default' },
        { title: 'Զեղչված սեղաններ', value: '7', label: 'label-info' },
        { title: 'Պարտքով փակված սեղաններ', value: '4', label: 'label-primary' },
        { title: 'Փակված սեղաններ', value: '31', label: 'label-primary' }
      ]
    }
  ];

  const cardMarkup = cards.map(card => `<div class="reportType col-md-4 col-sm-12">
    <aside class="profile-nav alt ${card.asideClass} report-card">
      <section class="panel">
        <div class="user-heading alt reportTitle">
          <h1 class="text-center">${card.title}</h1>
        </div>
        ${list(card.rows)}
      </section>
    </aside>
  </div>`).join('');

  return `<div class="reports-page">
    <section class="panel reports-filter-panel">
      <div class="panel-body">
        <form method="post" class="reports-filter-form">
          <div class="row m-bot15">
            <div class="input-group input-large col-sm-6 col-xs-12 m-bot15 header_filter" data-date-format="yyyy/mm/dd" id="noPadL">
              <input type="text" class="form-control dpd1" value="2026-07-01" name="datepicker_start_date">
              <span class="input-group-addon inputsDivider">ից</span>
              <input type="text" class="form-control dpd2" value="2026-07-03" name="datepicker_end_date">
            </div>

            <div class="input-group input-large padding-left0 col-sm-6 col-xs-12 header_filter">
              <div class="input-group bootstrap-timepicker">
                <span class="input-group-btn">
                  <button class="btn btn-default button-time-icon brn timeIcon1" type="button"><i class="icon-time"></i></button>
                </span>
                <input type="text" class="form-control timepicker-24 tp1 border-radius0" name="clock_start" value="00:00:00">
              </div>
              <span class="input-group-addon border-radius0 inputsDivider">ից</span>
              <div class="input-group bootstrap-timepicker">
                <input type="text" class="form-control timepicker-24 tp2 border-radius0" name="clock_end" value="23:59:59">
                <span class="input-group-btn">
                  <button class="btn btn-default button-time-icon bln timeIcon2" type="button"><i class="icon-time"></i></button>
                </span>
              </div>
              <span class="input-group-btn">
                <button class="btn btn-md btn-info padding5" type="submit" name="datetimepickerSubmit">Ֆիլտրել</button>
              </span>
            </div>

            <div class="col-xs-12 input-group food_date_print">
              <select class="form-control breakPointSelect" name="breakPointSelect">
                <option>03.07.2026 09:00:00 - 03.07.2026 17:00:00</option>
                <option>02.07.2026 09:00:00 - 02.07.2026 23:50:00</option>
                <option>01.07.2026 10:15:00 - 01.07.2026 23:40:00</option>
              </select>
              <span class="input-group-btn">
                <button class="btn btn-info smart-select padding5" type="submit" name="breakPointSubmit">Փնտրել ըստ օրվա վերջի</button>
                <button id="print_report" class="btn btn-danger padding5 pull-right" type="button">Տպել հաշվետվություն</button>
              </span>
            </div>
          </div>
        </form>
      </div>
    </section>

    <form role="form" method="get" class="reports-excel-form">
      <a href="#" class="btn btn-default reports-excel-btn">Excel <i class="fa fa-table"></i></a>
    </form>

    <div class="row report_main_content">${cardMarkup}</div>
  </div>`;
}

function reportsCheckContent() {
  const items = [
    { name: 'Խորոված հավի մսով', count: 2, price: 3200, sale: 0, cost: 4200 },
    { name: 'Կարտոֆիլ ֆրի', count: 3, price: 900, sale: 0, cost: 1100 },
    { name: 'Ամառային աղցան', count: 1, price: 1800, sale: 10, cost: 950 },
    { name: 'Լիմոնադ', count: 2, price: 800, sale: 0, cost: 500 }
  ];
  const rows = items.map(item => {
    const price = item.sale > 0 ? item.price - (item.price * item.sale / 100) : item.price;
    const total = price * item.count;
    const profit = total - item.cost;
    return `<tr>
      <td>${item.name}</td>
      <td>${item.count}</td>
      <td>${item.sale > 0 ? `<span style="text-decoration: line-through">${item.price}</span> ${price}` : price}</td>
      <td>${item.sale}%</td>
      <td>${total}</td>
      <td>${item.cost}</td>
      <td>${profit}</td>
    </tr>`;
  }).join('');

  return `<div class="row reports-check-page">
    <div class="col-xs-12">
      <section class="panel">
        <div class="panel-body">
          <div class="row invoice-list">
            <div class="col-lg-4 col-sm-4">
              <h4>Տեղեկություններ</h4>
              <p>
                Կտրոն: #97708<br>
                Սեղան: 4<br>
                Սրահ: Դրսի սրահ<br>
                Ամսաթիվ: 2026-06-23 11:04<br>
              </p>
            </div>
          </div>

          <h2 class="text-center">Պատվերներ</h2>
          <table class="table table-striped table-hover">
            <thead>
              <tr>
                <th>Անվանում</th>
                <th>Քանակ</th>
                <th>Արժեք</th>
                <th>Զեղչ</th>
                <th>Ընդհ․ արժեք</th>
                <th>Ընդհ․ ինքնարժեք</th>
                <th>Շահույթ</th>
              </tr>
            </thead>
            <tbody>${rows}</tbody>
          </table>

          <hr>
          <h2 class="text-center">Ջնջված պատվերներ</h2>
          <table class="table table-striped table-hover">
            <thead><tr><th>Անվանում</th><th>Քանակ</th><th>Ջնջման ամսաթիվ</th></tr></thead>
            <tbody>
              <tr><td>Թան</td><td>1</td><td>23.06.2026 10:58</td></tr>
            </tbody>
          </table>

          <div class="row">
            <div class="col-lg-4 invoice-block pull-right">
              <ul class="unstyled amounts">
                <li><strong>Պատվերի արժեք:</strong> 12700</li>
                <li><strong>Սեղանի տեսակը:</strong> Standard</li>
                <li><strong>Զեղչ:</strong> 10%</li>
                <li><strong>Ընդհանուր:</strong> 12240</li>
              </ul>
            </div>
            <div class="col-lg-4 invoice-block">
              <h4>Payment types</h4>
              <ul class="unstyled amounts">
                <li><strong>Տերմինալ:</strong> 500</li>
                <li><strong>Կանխիկ:</strong> 11740</li>
              </ul>
            </div>
          </div>
        </div>
      </section>
    </div>
  </div>`;
}

function reportsTablesHistoryContent() {
  const statusFilters = [
    { value: 'all', label: 'Բոլորը' },
    { value: 'closedTable', label: 'Փակված սեղաններ' },
    { value: 'removedOrderTable', label: 'Ջնջված պատվերով սեղան' },
    { value: 'zeroTable', label: 'Զրոյացված սեղան' },
    { value: 'discountedTable', label: 'Զեղչված սեղան' },
    { value: 'discountedProductTable', label: 'Զեղչված ապրանքով սեղան' },
    { value: 'debtClosedTable', label: 'Պարտքով փակված սեղան' },
    { value: 'emptyTable', label: 'Դատարկ սեղան' }
  ];

  const columns = [
    'uniqId',
    'tableName',
    'createDate',
    'order_mod_date',
    'timeDiff',
    'waiter',
    'costPrice',
    'totalPrice',
    'payed',
    'commissionValue',
    'sale',
    'discountedAmount',
    'sale_description',
    'clientCount',
    'hdm',
    'comment',
    'tip',
    'fiscalNumber'
  ];

  const rows = [
    {
      statusClass: 'closed-table',
      uniqId: '97708',
      tableName: 'Սեղան 4 / Դրսի սրահ',
      createDate: '23.06.26 11:04',
      order_mod_date: '23.06.26 12:18',
      timeDiff: '01:14',
      waiter: 'Arpi',
      costPrice: '4200',
      totalPrice: '12700',
      payed: '12240',
      commissionValue: '500',
      sale: '10',
      discountedAmount: '460',
      sale_description: 'Մշտական հաճախորդ',
      clientCount: '3',
      hdm: 'Այո',
      comment: 'Կտրոն #97708',
      tip: '900',
      fiscalNumber: '420172',
      mootq: false
    },
    {
      statusClass: 'removed-order-table',
      uniqId: '97706',
      tableName: 'Սեղան 5 / Երկրորդ հարկ',
      createDate: '16.06.26 15:46',
      order_mod_date: '16.06.26 16:02',
      timeDiff: '00:16',
      waiter: 'Arpi',
      costPrice: '7800',
      totalPrice: '15500',
      payed: '15500',
      commissionValue: '0',
      sale: '0',
      discountedAmount: '0',
      sale_description: '',
      clientCount: '4',
      hdm: 'Այո',
      comment: 'Ջնջված պատվեր',
      tip: '0',
      fiscalNumber: '420168',
      mootq: false
    },
    {
      statusClass: 'zero-table',
      uniqId: '97703',
      tableName: 'Սեղան 12 / Դրսի սրահ',
      createDate: '16.06.26 15:27',
      order_mod_date: '16.06.26 15:31',
      timeDiff: '00:04',
      waiter: 'Tovmasyan',
      costPrice: '0',
      totalPrice: '0',
      payed: '0',
      commissionValue: '0',
      sale: '0',
      discountedAmount: '0',
      sale_description: '',
      clientCount: '0',
      hdm: 'Ոչ',
      comment: 'Զրոյացված',
      tip: '0',
      fiscalNumber: '',
      mootq: false
    },
    {
      statusClass: 'discounted-table',
      uniqId: '97699',
      tableName: 'Սեղան 8 / Հիմնական սրահ',
      createDate: '16.06.26 15:12',
      order_mod_date: '16.06.26 15:28',
      timeDiff: '00:16',
      waiter: 'smart',
      costPrice: '5900',
      totalPrice: '14650',
      payed: '13200',
      commissionValue: '0',
      sale: '15',
      discountedAmount: '1450',
      sale_description: 'Ծննդյան զեղչ',
      clientCount: '5',
      hdm: 'Այո',
      comment: '',
      tip: '500',
      fiscalNumber: '420160',
      mootq: false
    },
    {
      statusClass: 'discounted-product-table',
      uniqId: '97697',
      tableName: 'Սեղան 3 / Դրսի սրահ',
      createDate: '16.06.26 15:05',
      order_mod_date: '16.06.26 15:24',
      timeDiff: '00:19',
      waiter: 'Arpi',
      costPrice: '3100',
      totalPrice: '8250',
      payed: '7950',
      commissionValue: '0',
      sale: '0',
      discountedAmount: '300',
      sale_description: 'Ապրանքի զեղչ',
      clientCount: '2',
      hdm: 'Այո',
      comment: '',
      tip: '350',
      fiscalNumber: '420158',
      mootq: false
    },
    {
      statusClass: 'debt-table',
      uniqId: '97696',
      tableName: 'Mootq Արագ սնունդ',
      createDate: '16.06.26 14:48',
      order_mod_date: '16.06.26 15:20',
      timeDiff: '00:32',
      waiter: 'Mootq',
      costPrice: '2200',
      totalPrice: '4950',
      payed: '0',
      commissionValue: '0',
      sale: '0',
      discountedAmount: '0',
      sale_description: '',
      clientCount: '1',
      hdm: 'Ոչ',
      comment: 'Պարտքով փակված',
      tip: '0',
      fiscalNumber: '',
      mootq: true
    },
    {
      statusClass: 'empty-table',
      uniqId: '97695',
      tableName: 'Սեղան 1 / Հիմնական սրահ',
      createDate: '16.06.26 14:30',
      order_mod_date: '16.06.26 14:36',
      timeDiff: '00:06',
      waiter: 'smart',
      costPrice: '0',
      totalPrice: '0',
      payed: '0',
      commissionValue: '0',
      sale: '0',
      discountedAmount: '0',
      sale_description: '',
      clientCount: '0',
      hdm: 'Ոչ',
      comment: 'Դատարկ սեղան',
      tip: '0',
      fiscalNumber: '',
      mootq: false
    }
  ];

  const filterButtons = statusFilters.map((filter, index) => `<button type="submit" class="filterHistoryRow" value="${filter.value}">
    ${index === 0 ? '' : '<div class="colorMarker"></div>'}
    ${filter.label}
  </button>`).join('');

  const headerCells = columns.map(column => `<th>${column} <span class="sort-arrows">↕</span></th>`).join('');
  const filterCells = columns.map(column => {
    if (column === 'hdm') {
      return '<td><select class="form-control"><option></option><option>Այո</option><option>Ոչ</option></select></td>';
    }
    const type = ['uniqId', 'sale', 'clientCount'].includes(column) ? 'number' : 'text';
    return `<td><input class="form-control" type="${type}"></td>`;
  }).join('');
  const totalCells = columns.map((column, index) => {
    const totals = {
      costPrice: '23200',
      totalPrice: '56050',
      payed: '48890',
      commissionValue: '500',
      sale: '25',
      discountedAmount: '2210',
      clientCount: '15',
      tip: '1750'
    };
    if (index === 0) return '<td>Total</td>';
    return `<td>${totals[column] || ''}</td>`;
  }).join('');

  const tableRows = rows.map(row => `<tr class="moreInfo ${row.statusClass}" data-order="${row.uniqId}">
    ${columns.map(column => `<td>${row[column]}</td>`).join('')}
    <td class="cog_btns_td historyGridActions">
      <div class="flexBtns">
        <a href="#tipModal" class="editTip btn btn-xs btn-warning" title="Խմբագրել թեյավճարը" data-tip="${row.tip}" data-toggle="modal"><i class="fa fa-edit"></i></a>
        <a href="reports-check.html" class="btn btn-info btn-xs profit" data-order="${row.uniqId}"><i class="icon-info-sign"></i></a>
        ${row.mootq ? '<a href="#mootqCustomerInfoModal" class="btn btn-primary btn-xs mootqCustomerInfo" title="Հաճախորդի տվյալներ" data-toggle="modal"><i class="fa fa-user"></i></a>' : ''}
      </div>
    </td>
  </tr>`).join('');

  return `<div class="reports-table-history-page">
    <div class="row">
      <div class="col-xs-12">
        <section class="panel">
          <div class="panel-body">
            <form method="get" class="table-history-filter-form">
              <div class="row m-bot15">
                <div class="input-group input-large col-sm-6 col-xs-12 m-bot15 header_filter" data-date-format="yyyy/mm/dd" id="tableHistoryDateRange">
                  <input type="text" class="form-control dpd1" value="2026-07-01" name="datepicker_start_date">
                  <span class="input-group-addon inputsDivider">ից</span>
                  <input type="text" class="form-control dpd2" value="2026-07-03" name="datepicker_end_date">
                </div>

                <div class="input-group input-large padding-left0 col-sm-6 col-xs-12 header_filter header2">
                  <div class="input-group bootstrap-timepicker">
                    <span class="input-group-btn">
                      <button class="btn btn-default button-time-icon brn timeIcon1" type="button"><i class="icon-time"></i></button>
                    </span>
                    <input type="text" class="form-control timepicker-24 tp1 border-radius0" name="clock_start" value="00:00:00">
                  </div>
                  <span class="input-group-addon border-radius0 inputsDivider">ից</span>
                  <div class="input-group bootstrap-timepicker">
                    <input type="text" class="form-control timepicker-24 tp2 border-radius0" name="clock_end" value="23:59:59">
                    <span class="input-group-btn">
                      <button class="btn btn-default button-time-icon bln timeIcon2" type="button"><i class="icon-time"></i></button>
                    </span>
                  </div>
                  <span class="input-group-btn">
                    <button class="btn btn-md btn-info padding5" type="submit" name="datetimepickerSubmit">Ֆիլտրել</button>
                  </span>
                </div>

                <div class="col-xs-12 input-group food_date_print">
                  <select class="form-control breakPointSelect" name="breakPointSelect">
                    <option>03.07.2026 09:00:00 - 03.07.2026 17:00:00</option>
                    <option>02.07.2026 09:00:00 - 02.07.2026 23:50:00</option>
                  </select>
                  <span class="input-group-btn buttonsLine">
                    <button class="btn btn-info smart-select padding5" type="submit" name="breakPointSubmit">Փնտրել ըստ օրվա վերջի</button>
                    <button id="tableHistoryPrintReport" class="btn btn-danger padding5 pull-right" type="button">Տպել հաշվետվություն</button>
                  </span>
                </div>
              </div>

              <div class="row">
                <div class="col-xs-12 filterButtonsContainer">${filterButtons}</div>
              </div>
            </form>

            <a class="dt-button buttons-excel buttons-html5 table-history-excel" href="#">
              <span><button class="btn btn-primary"><img src="assets/img/icons/ExcelLogo.svg" alt=""> Excel</button></span>
            </a>

            <div class="table-history-grid-wrap dataTables_wrapper form-inline">
              <div class="row table-history-grid-top">
                <div class="col-sm-6">
                  <div class="dataTables_length">
                    <label>
                      <select class="form-control input-sm">
                        <option>20</option>
                        <option>50</option>
                        <option>100</option>
                      </select>
                      քանակ
                    </label>
                  </div>
                </div>
                <div class="col-sm-6 text-right table-history-search">
                  <label>Փնտրել:
                    <input type="text" class="form-control input-sm">
                  </label>
                </div>
              </div>

              <div class="table-history-scroll">
                <table class="table table-striped table-bordered" id="TableHistoryGridTable">
                  <thead>
                    <tr style="background:#e5e5e5;">
                      ${headerCells}
                      <th class="cogs"><i class="fa fa-cogs" aria-hidden="true"></i></th>
                    </tr>
                    <tr class="filters" style="background:white;">
                      ${filterCells}
                      <td></td>
                    </tr>
                  </thead>
                  <tbody>${tableRows}</tbody>
                  <tfoot>
                    <tr>${totalCells}<td></td></tr>
                  </tfoot>
                </table>
              </div>

              <div class="row table-history-grid-bottom">
                <div class="col-sm-5">
                  <div class="dataTables_info">Ցուցադրված է 1-ից 7-ը 7 գրառումից</div>
                </div>
                <div class="col-sm-7">
                  <div class="dataTables_paginate paging_bootstrap pagination">
                    <ul><li class="prev disabled"><a href="#">← Նախորդ</a></li><li class="active"><a href="#">1</a></li><li class="next disabled"><a href="#">Հաջորդ →</a></li></ul>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>

    <div class="modal fade" id="orderMoreInfoModal" role="dialog">
      <div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-body"></div></div></div>
    </div>

    <div class="modal fade" id="mootqCustomerInfoModal" role="dialog">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <h4 class="modal-title">Հաճախորդի տվյալներ</h4>
          </div>
          <div class="modal-body">
            <p><b>Պատվերի ID:</b> 97696</p>
            <p><b>Հաճախորդ:</b> Ani Hakobyan</p>
            <p><b>Հեռախոս:</b> +374 99 000000</p>
            <p><b>Մոտենալու ժամանակ:</b> 16.06.26 15:20</p>
          </div>
          <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Փակել</button></div>
        </div>
      </div>
    </div>

    <div id="tipModal" class="modal fade form-horizontal" role="dialog">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <h4 class="modal-title">Խմբագրել թեյավճարը</h4>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label class="control-label col-sm-4">Գումար:</label>
              <div class="col-sm-8">
                <input type="number" id="tipInput" class="form-control">
                <input type="hidden" id="orderInput" class="form-control">
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button>
            <button class="btn btn-success pull-right" id="tipBtn">Հաստատել</button>
          </div>
        </div>
      </div>
    </div>

    <input type="hidden" value="datepicker_start_date=2026-07-01&datepicker_end_date=2026-07-03" id="urlParameters">
    <input type="hidden" id="translationObjectInput" value='{}'>
  </div>`;
}

function reportsDeliveryContent() {
  const hiddenColumns = ['', '', '', ''];
  const columns = [
    'Չեկ',
    'Սեղ․ / սրահ',
    'Ամսաթիվ',
    'Առաքիչ',
    'Մատուցող',
    'Հաճախորդ',
    'Հասցե',
    'Հեռախոսահամար',
    'Ինքնարժեք',
    'Հաշիվ',
    'Վճարված',
    'Առաքման գումար',
    'Գումար',
    'Նկարագրություն'
  ];

  const allColumns = [...hiddenColumns, ...columns];
  const searchCells = allColumns.map((_, index) => {
    return index < hiddenColumns.length
      ? '<td class="delivery-hidden-col"><input type="text" class="form-control" placeholder="Փնտրել"></td>'
      : '<td><input type="text" class="form-control" placeholder="Փնտրել"></td>';
  }).join('');
  const headerCells = allColumns.map((column, index) => {
    return index < hiddenColumns.length
      ? '<th class="delivery-hidden-col"></th>'
      : `<th>${column} <span class="sort-arrows">↕</span></th>`;
  }).join('');
  const rows = [
    {
      order: '97708',
      table: 'Առաքում 4 / Delivery',
      date: '23.06.26 11:04',
      delivery: 'Գոռ',
      waiter: 'Arpi',
      client: 'Անի Հակոբյան',
      address: 'Կոմիտաս 42, բն. 18',
      phone: '+374 99 000000',
      cost: '4200',
      total: '12700',
      payed: '12240',
      deliveryFee: '500',
      amount: '700',
      comment: 'Առանց սոխի'
    },
    {
      order: '97706',
      table: 'Առաքում 2 / Delivery',
      date: '16.06.26 15:46',
      delivery: 'Արմեն',
      waiter: 'Arpi',
      client: 'Դավիթ Սարգսյան',
      address: 'Բաղրամյան 12',
      phone: '+374 91 123456',
      cost: '7800',
      total: '15500',
      payed: '15500',
      deliveryFee: '0',
      amount: '900',
      comment: ''
    },
    {
      order: '97703',
      table: 'Առաքում 7 / Delivery',
      date: '16.06.26 15:27',
      delivery: 'Գոռ',
      waiter: 'Tovmasyan',
      client: 'Մարիամ',
      address: 'Աբովյան 6',
      phone: '+374 77 654321',
      cost: '3100',
      total: '8250',
      payed: '7950',
      deliveryFee: '300',
      amount: '600',
      comment: 'Վճարումը քարտով'
    },
    {
      order: '97699',
      table: 'Առաքում 1 / Delivery',
      date: '16.06.26 15:12',
      delivery: 'Արմեն',
      waiter: 'smart',
      client: 'Սուրեն',
      address: 'Մաշտոց 20',
      phone: '+374 93 777888',
      cost: '5900',
      total: '14650',
      payed: '13200',
      deliveryFee: '0',
      amount: '800',
      comment: 'Զեղչված պատվեր'
    },
    {
      order: '97696',
      table: 'Mootq Արագ սնունդ',
      date: '16.06.26 14:48',
      delivery: 'Mootq',
      waiter: 'Mootq',
      client: 'Լիլիթ',
      address: 'Տերյան 52',
      phone: '+374 55 222333',
      cost: '2200',
      total: '4950',
      payed: '0',
      deliveryFee: '0',
      amount: '500',
      comment: 'Պարտքով'
    }
  ];
  const bodyRows = rows.map(row => `<tr data-order="${row.order}">
    <td class="delivery-hidden-col"></td>
    <td class="delivery-hidden-col"></td>
    <td class="delivery-hidden-col"></td>
    <td class="delivery-hidden-col"></td>
    <td class="moreInfo"><a href="reports-check.html" class="text-primary">${row.order}</a></td>
    <td class="moreInfo">${row.table}</td>
    <td class="moreInfo">${row.date}</td>
    <td class="moreInfo">${row.delivery}</td>
    <td class="moreInfo">${row.waiter}</td>
    <td class="moreInfo">${row.client}</td>
    <td class="moreInfo">${row.address}</td>
    <td class="moreInfo">${row.phone}</td>
    <td class="moreInfo">${row.cost}</td>
    <td class="moreInfo">${row.total}</td>
    <td class="moreInfo">${row.payed}</td>
    <td class="moreInfo">${row.deliveryFee}</td>
    <td class="moreInfo">${row.amount}</td>
    <td class="moreInfo">${row.comment}</td>
  </tr>`).join('');

  return `<div class="reports-delivery-page">
    <div class="row">
      <div class="col-xs-12">
        <section class="panel">
          <div class="panel-body">
            <form method="get" class="delivery-filter-form">
              <div class="row m-bot15">
                <div class="input-group input-large col-sm-6 col-xs-12 m-bot15 header_filter" data-date-format="yyyy/mm/dd" id="deliveryDateRange">
                  <input type="text" class="form-control dpd1" value="2026-07-03" name="datepicker_start_date">
                  <span class="input-group-addon inputsDivider">ից</span>
                  <input type="text" class="form-control dpd2" value="2026-07-03" name="datepicker_end_date">
                </div>

                <div class="input-group input-large padding-left0 col-sm-6 col-xs-12 header_filter header2">
                  <div class="input-group bootstrap-timepicker">
                    <span class="input-group-btn">
                      <button class="btn btn-default button-time-icon brn timeIcon1" type="button"><i class="icon-time"></i></button>
                    </span>
                    <input type="text" class="form-control timepicker-24 tp1 border-radius0" name="clock_start" value="00:00:00">
                  </div>
                  <span class="input-group-addon border-radius0 inputsDivider">ից</span>
                  <div class="input-group bootstrap-timepicker">
                    <input type="text" class="form-control timepicker-24 tp2 border-radius0" name="clock_end" value="23:59:59">
                    <span class="input-group-btn">
                      <button class="btn btn-default button-time-icon bln timeIcon2" type="button"><i class="icon-time"></i></button>
                    </span>
                  </div>
                  <span class="input-group-btn">
                    <button class="btn btn-md btn-info padding5 filterButton" type="submit" name="datetimepickerSubmit">Ֆիլտրել</button>
                  </span>
                </div>

                <div class="col-xs-12 input-group food_date_print">
                  <select class="form-control breakPointSelect" name="breakPointSelect">
                    <option>15.06.2026 23:48:57 - 03.07.2026 17:53:29</option>
                    <option>03.07.2026 09:00:00 - 03.07.2026 17:00:00</option>
                  </select>
                  <span class="input-group-btn buttonsLine">
                    <button class="btn btn-info smart-select padding5 showByShift" type="submit" name="breakPointSubmit">Ցուցադրել ըստ հերթափոխի</button>
                    <button id="deliveryShowReport" class="btn btn-warning padding5 pull-right" type="button">Տալ հաշվետվություն</button>
                  </span>
                </div>
              </div>
            </form>

            <div class="row reports-delivery-tools">
              <div class="col-sm-6">
                <button class="btn btn-warning m-r-10 printButton"><img src="assets/img/icons/printIcon.svg" alt="">Տպել</button>
                <button class="btn btn-primary excelButton"><img src="assets/img/icons/ExcelLogo.svg" alt="">Excel</button>
              </div>
              <div class="col-sm-6 text-right reports-delivery-search">
                <label>Փնտրել</label>
                <input type="text" class="form-control" placeholder="">
              </div>
            </div>

            <div class="grid-view">
              <table class="table table-bordered reports-delivery-table table-responsive" id="deliveryHistoryTable">
                <thead>
                  <tr>${headerCells}</tr>
                  <tr class="column-search">${searchCells}</tr>
                </thead>
                <tbody>${bodyRows}</tbody>
                <tfoot>
                  <tr>
                    <th class="delivery-hidden-col"></th>
                    <th class="delivery-hidden-col"></th>
                    <th class="delivery-hidden-col"></th>
                    <th class="delivery-hidden-col"></th>
                    <th>Ընդհանուր</th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th class="total-sum">23200</th>
                    <th class="total-sum">56050</th>
                    <th class="total-sum">48890</th>
                    <th class="total-sum">800</th>
                    <th></th>
                    <th></th>
                  </tr>
                </tfoot>
              </table>
            </div>

            <div class="row reports-delivery-grid-bottom">
              <div class="col-sm-5">
                <div class="dataTables_info">Ցուցադրված է 1-ից 5-ը 5 գրառումից</div>
              </div>
              <div class="col-sm-7">
                <div class="dataTables_paginate paging_bootstrap pagination">
                  <ul><li class="prev disabled"><a href="#">Նախորդ</a></li><li class="active"><a href="#">1</a></li><li class="next disabled"><a href="#">Հաջորդ</a></li></ul>
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>

    <div class="modal fade" id="sexannerModal" role="dialog">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-body">
            <div class="row invoice-list">
              <div class="col-sm-4">
                <h4>Տեղեկություններ</h4>
                <p>Սեղան: Առաքում 4<br>Սրահ: Delivery<br>Ամսաթիվ: 23.06.26 11:04</p>
              </div>
              <div class="col-sm-4 invoice-block pull-right">
                <ul class="unstyled amounts">
                  <li><strong>Պատվերի արժեք:</strong> 12700</li>
                  <li><strong>Հաստատագրված:</strong> 500</li>
                  <li><strong>Ընդհանուր:</strong> 12240</li>
                </ul>
              </div>
            </div>
            <h4 class="text-center">Պատվերներ</h4>
            <table class="table table-striped table-hover">
              <thead><tr><th>Անվանում</th><th>Քանակ</th><th>Արժեք</th><th>Ընդհ. արժեք</th><th>Ընդհ. ինքնարժեք</th><th>Շահույթ</th></tr></thead>
              <tbody>
                <tr><td>Խորոված հավի մսով</td><td>2</td><td>3200</td><td>6400</td><td>4200</td><td>2200</td></tr>
                <tr><td>Կարտոֆիլ ֆրի</td><td>3</td><td>900</td><td>2700</td><td>1100</td><td>1600</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <input type="hidden" class="excelData" value='[{"lineName":"Ծրագրի անվանում","lineValue":"Smart Rest"},{"lineName":"Ծրագրի բաժին","lineValue":"Հաշվետվություններ / Առաքման Պատմություն"}]'>
    <input type="hidden" id="deliveryLanguage" value="hy">
  </div>`;
}

function reportsMovedTablesContent() {
  const hiddenColumns = ['', '', '', ''];
  const columns = [
    'Չեկ',
    'Սեղ․ / սրահ',
    'Տեղափոխվել է',
    'Ամսաթիվ',
    'matucox(uhi)',
    'ինքնարժեք',
    'Հաշիվ',
    'Վճարված'
  ];

  const allColumns = [...hiddenColumns, ...columns];
  const headerCells = allColumns.map((column, index) => index < hiddenColumns.length
    ? '<th class="moved-hidden-col"></th>'
    : `<th>${column} <span class="sort-arrows">↕</span></th>`).join('');
  const searchCells = allColumns.map((_, index) => index < hiddenColumns.length
    ? '<td class="moved-hidden-col"><input type="text" class="form-control" placeholder="Փնտրել"></td>'
    : '<td><input type="text" class="form-control" placeholder="Փնտրել"></td>').join('');
  const rows = [
    {
      order: '97708',
      table: '3 / Ներսի սրահ',
      movedTo: '7 / Դրսի սրահ',
      date: '03.07.26 17:20',
      waiter: 'Arpi',
      cost: '4200',
      total: '12700',
      payed: '12240',
      className: ''
    },
    {
      order: '97706',
      table: '12 / Առաջին հարկ',
      movedTo: '5 / Առաջին հարկ',
      date: '03.07.26 16:44',
      waiter: 'Tovmasyan',
      cost: '7800',
      total: '15500',
      payed: '15500',
      className: 'active'
    },
    {
      order: '97703',
      table: 'Արագ սնունդ / Fast Food',
      movedTo: '2 / Ներսի սրահ',
      date: '03.07.26 15:27',
      waiter: 'Mootq',
      cost: '3100',
      total: '8250',
      payed: '7950',
      className: 'warning'
    },
    {
      order: '97699',
      table: '8 / Դրսի սրահ',
      movedTo: '10 / Դրսի սրահ',
      date: '02.07.26 21:12',
      waiter: 'smart',
      cost: '5900',
      total: '14650',
      payed: '13200',
      className: 'myinfo_bg'
    },
    {
      order: '97696',
      table: '4 / Երկրորդ հարկ',
      movedTo: '9 / Երկրորդ հարկ',
      date: '02.07.26 19:48',
      waiter: 'Անի',
      cost: '2200',
      total: '4950',
      payed: '0',
      className: 'danger'
    }
  ];

  const bodyRows = rows.map(row => `<tr data-order="${row.order}" class="${row.className} moreInfo">
    <td class="moved-hidden-col"></td>
    <td class="moved-hidden-col"></td>
    <td class="moved-hidden-col"></td>
    <td class="moved-hidden-col"></td>
    <td><a href="reports-check.html" class="text-primary">${row.order}</a></td>
    <td>${row.table}</td>
    <td>${row.movedTo}</td>
    <td>${row.date}</td>
    <td>${row.waiter}</td>
    <td>${row.cost}</td>
    <td>${row.total}</td>
    <td>${row.payed}</td>
  </tr>`).join('');

  return `<div class="reports-moved-tables-page">
    <div class="row">
      <div class="col-xs-12">
        <section class="panel">
          <div class="panel-body">
            <form method="get" class="moved-tables-filter-form">
              <div class="row m-bot15">
                <div class="input-group input-large col-sm-6 col-xs-12 m-bot15 header_filter" data-date-format="yyyy/mm/dd" id="movedTablesDateRange">
                  <input type="text" class="form-control dpd1" value="2026-07-03" name="datepicker_start_date">
                  <span class="input-group-addon inputsDivider">ից</span>
                  <input type="text" class="form-control dpd2" value="2026-07-03" name="datepicker_end_date">
                </div>

                <div class="input-group input-large padding-left0 col-sm-6 col-xs-12 header_filter header2">
                  <div class="input-group bootstrap-timepicker">
                    <span class="input-group-btn">
                      <button class="btn btn-default button-time-icon brn timeIcon1" type="button"><i class="icon-time"></i></button>
                    </span>
                    <input type="text" class="form-control timepicker-24 tp1 border-radius0" name="clock_start" value="00:00:00">
                  </div>
                  <span class="input-group-addon border-radius0 inputsDivider">ից</span>
                  <div class="input-group bootstrap-timepicker">
                    <input type="text" class="form-control timepicker-24 tp2 border-radius0" name="clock_end" value="23:59:59">
                    <span class="input-group-btn">
                      <button class="btn btn-default button-time-icon bln timeIcon2" type="button"><i class="icon-time"></i></button>
                    </span>
                  </div>
                  <span class="input-group-btn">
                    <button class="btn btn-md btn-info padding5 filterButton" type="submit" name="datetimepickerSubmit">Ֆիլտրել</button>
                  </span>
                </div>

                <div class="col-xs-12 input-group food_date_print">
                  <select class="form-control breakPointSelect" name="breakPointSelect">
                    <option>15.06.2026 23:48:57 - 03.07.2026 17:53:29</option>
                    <option>03.07.2026 09:00:00 - 03.07.2026 17:00:00</option>
                  </select>
                  <span class="input-group-btn buttonsLine">
                    <button class="btn btn-info smart-select padding5 showByShift" type="submit" name="breakPointSubmit">Ցուցադրել ըստ հերթափոխի</button>
                    <button id="movedTablesShowReport" class="btn btn-warning padding5 pull-right" type="button">Տալ հաշվետվություն</button>
                  </span>
                </div>
              </div>
            </form>

            <div class="row reports-moved-tables-tools">
              <div class="col-sm-6">
                <button class="btn btn-warning m-r-10 printButton"><img src="assets/img/icons/printIcon.svg" alt="">Տպել</button>
                <button class="btn btn-primary excelButton"><img src="assets/img/icons/ExcelLogo.svg" alt="">Excel</button>
              </div>
              <div class="col-sm-6 text-right reports-moved-tables-search">
                <label>Փնտրել</label>
                <input type="text" class="form-control" placeholder="">
              </div>
            </div>

            <div class="grid-view">
              <table class="table table-advance table-bordered reports-moved-tables-table table-responsive mytable" id="movedTablesHistoryTable">
                <thead>
                  <tr>${headerCells}</tr>
                  <tr class="column-search">${searchCells}</tr>
                </thead>
                <tbody>${bodyRows}</tbody>
                <tfoot>
                  <tr>
                    <th class="moved-hidden-col"></th>
                    <th class="moved-hidden-col"></th>
                    <th class="moved-hidden-col"></th>
                    <th class="moved-hidden-col"></th>
                    <th>Ընդհանուր</th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th class="total-sum">23200</th>
                    <th class="total-sum">56050</th>
                    <th class="total-sum">48890</th>
                  </tr>
                </tfoot>
              </table>
            </div>

            <div class="row reports-moved-tables-grid-bottom">
              <div class="col-sm-5">
                <div class="dataTables_info">Ցուցադրված է 1-ից 5-ը 5 գրառումից</div>
              </div>
              <div class="col-sm-7">
                <div class="dataTables_paginate paging_bootstrap pagination">
                  <ul><li class="prev disabled"><a href="#">Նախորդ</a></li><li class="active"><a href="#">1</a></li><li class="next disabled"><a href="#">Հաջորդ</a></li></ul>
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>

    <div class="modal fade" id="sexannerModal" role="dialog">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-body">
            <div class="row invoice-list">
              <div class="col-sm-4">
                <h4>Տեղեկություններ</h4>
                <p>Սեղան: 3<br>Սրահ: Ներսի սրահ<br>Ամսաթիվ: 03.07.26 17:20</p>
              </div>
              <div class="col-sm-4 invoice-block pull-right">
                <ul class="unstyled amounts">
                  <li><strong>Պատվերի արժեք: </strong>12700</li>
                  <li><strong>Տեղափոխվել է: </strong>7 սեղանից</li>
                  <li><strong>Ընդհանուր: </strong>12700</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <input type="hidden" class="excelData" value='[{"lineName":"Ծրագրի անվանում","lineValue":"Smart Rest"},{"lineName":"Ծրագրի բաժին","lineValue":"Հաշվետվություններ / Տեղափոխված Սեղանների Պատմություն"}]'>
  </div>`;
}

function reportsMovedItemsContent() {
  const columns = [
    { key: 'date', label: 'Ամսաթիվ', filter: 'text' },
    { key: 'oldOrder', label: 'Չեկ', filter: 'text' },
    { key: 'newOrder', label: 'Նոր չեկ', filter: 'text' },
    { key: 'oldTable', label: 'Հին սեղան', filter: 'text' },
    { key: 'newTable', label: 'Նոր սեղան', filter: 'text' },
    { key: 'item', label: 'Ապրանք', filter: 'select' },
    { key: 'count', label: 'Քանակ', filter: 'number' }
  ];
  const rows = [
    {
      date: '2026-07-03 17:20',
      oldOrder: '<a href="reports-check.html">#97708</a>',
      newOrder: '<a href="reports-check.html">#97711</a>',
      oldTable: '3 / Ներսի սրահ',
      newTable: '7 / Դրսի սրահ',
      item: 'Խորոված հավի մսով',
      count: '2'
    },
    {
      date: '2026-07-03 16:44',
      oldOrder: '<a href="reports-check.html">#97706</a>',
      newOrder: '<a href="reports-check.html">#97709</a>',
      oldTable: '12 / Առաջին հարկ',
      newTable: '5 / Առաջին հարկ',
      item: 'Կեսար աղցան',
      count: '1'
    },
    {
      date: '2026-07-03 15:27',
      oldOrder: '<a href="reports-check.html">#97703</a>',
      newOrder: '',
      oldTable: 'Արագ սնունդ / Fast Food',
      newTable: '2 / Ներսի սրահ',
      item: 'Կարտոֆիլ ֆրի',
      count: '3'
    },
    {
      date: '2026-07-02 21:12',
      oldOrder: '<a href="reports-check.html">#97699</a>',
      newOrder: '<a href="reports-check.html">#97701</a>',
      oldTable: '8 / Դրսի սրահ',
      newTable: '10 / Դրսի սրահ',
      item: 'Լիմոնադ',
      count: '4'
    },
    {
      date: '2026-07-02 19:48',
      oldOrder: '<a href="reports-check.html">#97696</a>',
      newOrder: '<a href="reports-check.html">#97698</a>',
      oldTable: '4 / Երկրորդ հարկ',
      newTable: '9 / Երկրորդ հարկ',
      item: 'Սուրճ',
      count: '2'
    }
  ];

  const headerCells = columns.map(column => `<th>${column.label} <span class="sort-arrows">↕</span></th>`).join('');
  const filterCells = columns.map(column => {
    if (column.filter === 'select') {
      return `<td class="dropDown_type">
        <select class="form-control">
          <option value=""></option>
          <option>Խորոված հավի մսով</option>
          <option>Կեսար աղցան</option>
          <option>Կարտոֆիլ ֆրի</option>
          <option>Լիմոնադ</option>
          <option>Սուրճ</option>
        </select>
      </td>`;
    }

    return `<td><input type="${column.filter}" class="form-control" placeholder="Փնտրել"></td>`;
  }).join('');
  const bodyRows = rows.map(row => `<tr>
    ${columns.map(column => `<td>${row[column.key]}</td>`).join('')}
  </tr>`).join('');

  return `<div class="reports-moved-items-page">
    <div class="row">
      <div class="col-xs-12">
        <section class="panel">
          <div class="panel-body">
            <form method="get" class="moved-items-filter-form">
              <div class="row m-bot15">
                <div class="input-group input-large col-sm-6 col-xs-12 m-bot15 header_filter" data-date-format="yyyy/mm/dd" id="movedItemsDateRange">
                  <input type="text" class="form-control dpd1" value="2026-07-03" name="datepicker_start_date">
                  <span class="input-group-addon inputsDivider">ից</span>
                  <input type="text" class="form-control dpd2" value="2026-07-03" name="datepicker_end_date">
                </div>

                <div class="input-group input-large padding-left0 col-sm-6 col-xs-12 header_filter header2">
                  <div class="input-group bootstrap-timepicker">
                    <span class="input-group-btn">
                      <button class="btn btn-default button-time-icon brn timeIcon1" type="button"><i class="icon-time"></i></button>
                    </span>
                    <input type="text" class="form-control timepicker-24 tp1 border-radius0" name="clock_start" value="00:00:00">
                  </div>
                  <span class="input-group-addon border-radius0 inputsDivider">ից</span>
                  <div class="input-group bootstrap-timepicker">
                    <input type="text" class="form-control timepicker-24 tp2 border-radius0" name="clock_end" value="23:59:59">
                    <span class="input-group-btn">
                      <button class="btn btn-default button-time-icon bln timeIcon2" type="button"><i class="icon-time"></i></button>
                    </span>
                  </div>
                  <span class="input-group-btn">
                    <button class="btn btn-md btn-info padding5 filterButton" type="submit" name="datetimepickerSubmit">Ֆիլտրել</button>
                  </span>
                </div>

                <div class="col-xs-12 input-group food_date_print">
                  <select class="form-control breakPointSelect" name="breakPointSelect">
                    <option>15.06.2026 23:48:57 - 03.07.2026 17:53:29</option>
                    <option>03.07.2026 09:00:00 - 03.07.2026 17:00:00</option>
                  </select>
                  <span class="input-group-btn buttonsLine">
                    <button class="btn btn-info smart-select padding5 showByShift" type="submit" name="breakPointSubmit">Ցուցադրել ըստ հերթափոխի</button>
                    <button id="movedItemsShowReport" class="btn btn-warning padding5 pull-right" type="button">Տալ հաշվետվություն</button>
                  </span>
                </div>
              </div>
            </form>

            <div class="row reports-moved-items-tools">
              <div class="col-sm-6">
                <div id="toolPager">
                  <label>Ցուցադրել</label>
                  <select class="form-control">
                    <option>10</option>
                    <option>25</option>
                    <option>50</option>
                    <option>100</option>
                  </select>
                  <span>գրառում</span>
                </div>
              </div>
              <div class="col-sm-6 text-right">
                <button class="btn btn-primary excelButton"><img src="assets/img/icons/ExcelLogo.svg" alt="">Excel</button>
              </div>
            </div>

            <div class="grid-view">
              <table class="table table-striped table-bordered reports-moved-items-table" id="MovedItemGridTable">
                <thead>
                  <tr>${headerCells}</tr>
                  <tr class="filters">${filterCells}</tr>
                </thead>
                <tbody>${bodyRows}</tbody>
              </table>
            </div>

            <div class="row reports-moved-items-grid-bottom">
              <div class="col-sm-5">
                <div class="summary">Ցուցադրված է 1-ից 5-ը 5 գրառումից</div>
              </div>
              <div class="col-sm-7">
                <ul class="pagination">
                  <li class="prev disabled"><a href="#">Նախորդ</a></li>
                  <li class="active"><a href="#">1</a></li>
                  <li class="next disabled"><a href="#">Հաջորդ</a></li>
                </ul>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>
  </div>`;
}

function reportsFoodContent() {
  const columns = [
    { key: 'title', label: 'Անվանում', footer: 'Ընդամենը' },
    { key: 'group', label: 'Բաժին', footer: '' },
    { key: 'place', label: 'Մենյու', footer: '' },
    { key: 'itemPrice', label: 'Գին', footer: '14900' },
    { key: 'cost', label: 'Ընդհ․ ինքնարժեք', footer: '21700' },
    { key: 'quantity', label: 'Քանակ', footer: '24' },
    { key: 'trade', label: 'Առևտուր', footer: '56100' },
    { key: 'commission', label: 'Սպաս. վճար', footer: '2050' },
    { key: 'income', label: 'Եկամուտ', footer: '34400' },
    { key: 'costPercent', label: 'Ինքն․ տոկոս', footer: '39' },
    { key: 'code', label: 'Կոդ', footer: '' }
  ];
  const rows = [
    {
      id: '101',
      title: 'Խորոված հավի մսով',
      group: 'Տաք ուտեստներ',
      place: 'Խոհանոց',
      itemPrice: '3200',
      cost: '8400',
      quantity: '4',
      trade: '12800',
      commission: '600',
      income: '4400',
      costPercent: '66',
      code: 'SR-001'
    },
    {
      id: '102',
      title: 'Կեսար աղցան',
      group: 'Աղցաններ',
      place: 'Խոհանոց',
      itemPrice: '2800',
      cost: '3500',
      quantity: '3',
      trade: '8400',
      commission: '350',
      income: '4900',
      costPercent: '42',
      code: 'SR-014'
    },
    {
      id: '103',
      title: 'Կարտոֆիլ ֆրի',
      group: 'Խավարտ',
      place: 'Խոհանոց',
      itemPrice: '900',
      cost: '1800',
      quantity: '6',
      trade: '5400',
      commission: '200',
      income: '3600',
      costPercent: '33',
      code: 'SR-021'
    },
    {
      id: '104',
      title: 'Լիմոնադ',
      group: 'Ըմպելիքներ',
      place: 'Բար',
      itemPrice: '1500',
      cost: '2400',
      quantity: '5',
      trade: '7500',
      commission: '300',
      income: '5100',
      costPercent: '32',
      code: 'BR-008'
    },
    {
      id: '105',
      title: 'Սուրճ',
      group: 'Տաք ըմպելիք',
      place: 'Բար',
      itemPrice: '1200',
      cost: '1600',
      quantity: '6',
      trade: '7200',
      commission: '250',
      income: '5600',
      costPercent: '22',
      code: 'BR-011'
    },
    {
      id: '106',
      title: 'Պիցցա Մարգարիտա',
      group: 'Պիցցա',
      place: 'Խոհանոց',
      itemPrice: '5300',
      cost: '4000',
      quantity: '2',
      trade: '14800',
      commission: '350',
      income: '10800',
      costPercent: '27',
      code: 'PZ-003'
    }
  ];

  const headerCells = columns.map(column => `<th>${column.label} <span class="sort-arrows">↕</span></th>`).join('');
  const filterCells = columns.map(column => `<td><input type="text" class="form-control" placeholder="Փնտրել" aria-label="${column.label}"></td>`).join('');
  const bodyRows = rows.map(row => `<tr data-id="${row.id}">
    ${columns.map(column => `<td>${row[column.key]}</td>`).join('')}
  </tr>`).join('');
  const footerCells = columns.map(column => `<td>${column.footer}</td>`).join('');

  return `<div class="reports-food-page">
    <div class="row">
      <div class="panel padd15 reports-food-shell">
        <header class="panel-heading">
          <ul class="nav nav-tabs menuButtonsContainer">
            <li class="active"><a href="?type=all">Ընդհանուր</a></li>
            <li><a href="?type=order">Սեղաններ/սրահներ</a></li>
            <li><a href="?type=delivery">Առաքում</a></li>
          </ul>
        </header>

        <div class="col-xs-12 reports-food-inner">
          <section class="panel">
            <div class="panel-body">
              <form method="get" class="food-filter-form">
                <div class="row m-bot15">
                  <div class="input-group input-large col-sm-6 col-xs-12 m-bot15 header_filter" data-date-format="yyyy/mm/dd" id="foodDateRange">
                    <input type="text" class="form-control dpd1" value="2026-07-03" name="datepicker_start_date">
                    <span class="input-group-addon inputsDivider">ից</span>
                    <input type="text" class="form-control dpd2" value="2026-07-03" name="datepicker_end_date">
                  </div>

                  <div class="input-group input-large padding-left0 col-sm-6 col-xs-12 header_filter header2">
                    <div class="input-group bootstrap-timepicker">
                      <span class="input-group-btn">
                        <button class="btn btn-default button-time-icon brn timeIcon1" type="button"><i class="icon-time"></i></button>
                      </span>
                      <input type="text" class="form-control timepicker-24 tp1 border-radius0" name="clock_start" value="00:00:00">
                    </div>
                    <span class="input-group-addon border-radius0 inputsDivider">ից</span>
                    <div class="input-group bootstrap-timepicker">
                      <input type="text" class="form-control timepicker-24 tp2 border-radius0" name="clock_end" value="23:59:59">
                      <span class="input-group-btn">
                        <button class="btn btn-default button-time-icon bln timeIcon2" type="button"><i class="icon-time"></i></button>
                      </span>
                    </div>
                    <span class="input-group-btn">
                      <button class="btn btn-md btn-info padding5 filterButton" type="submit" name="datetimepickerSubmit">Ֆիլտրել</button>
                    </span>
                  </div>

                  <div class="col-xs-12 input-group food_date_print">
                    <select class="form-control breakPointSelect" name="breakPointSelect">
                      <option>15.06.2026 23:48:57 - 03.07.2026 17:53:29</option>
                      <option>03.07.2026 09:00:00 - 03.07.2026 17:00:00</option>
                    </select>
                    <span class="input-group-btn buttonsLine">
                      <button class="btn btn-info smart-select padding5 showByShift" type="submit" name="breakPointSubmit">Ցուցադրել ըստ հերթափոխի</button>
                      <button id="foodShowReport" class="btn btn-warning padding5 pull-right" type="button">Տալ հաշվետվություն</button>
                    </span>
                  </div>
                </div>
              </form>

              <div class="row reports-food-buttons">
                <div class="col-xs-12">
                  <button class="btn btn-warning printButton" id="print-table">Տպել <i class="fa fa-print" aria-hidden="true"></i></button>
                  <button class="btn btn-primary excelButton"><img src="assets/img/icons/ExcelLogo.svg" alt="">Excel</button>
                  <button class="btn btn-default detailedExcelButton"><img src="assets/img/icons/ExcelLogo.svg" alt="">Excel detailed</button>
                  <button class="btn btn-primary mobile-filter-button" id="mobileFilterButton">filter</button>
                </div>
              </div>

              <div class="row reports-food-grid-tools">
                <div class="col-md-6">
                  <div id="toolPager">
                    <label>Ցուցադրել</label>
                    <select class="form-control">
                      <option>10</option>
                      <option>25</option>
                      <option>50</option>
                      <option>100</option>
                    </select>
                    <span>գրառում</span>
                  </div>
                </div>
                <div class="col-md-6 filters globalSearchBlock text-right">
                  <input type="text" id="foodSearchForm-globalSearch" class="form-control" name="FoodSearchForm[globalSearch]" placeholder="Փնտրել">
                </div>
              </div>

              <div class="grid-view">
                <table class="table table-striped table-bordered reports-food-table" id="FoodGridTable">
                  <thead>
                    <tr>${headerCells}</tr>
                    <tr class="filters">${filterCells}</tr>
                  </thead>
                  <tbody>${bodyRows}</tbody>
                  <tfoot>
                    <tr class="table-footer">${footerCells}</tr>
                  </tfoot>
                </table>
              </div>

              <div class="row reports-food-grid-bottom">
                <div class="col-sm-5">
                  <div class="summary">Ցուցադրված է 1-ից 6-ը 6 գրառումից</div>
                </div>
                <div class="col-sm-7">
                  <ul class="pagination">
                    <li class="prev disabled"><a href="#">Նախորդ</a></li>
                    <li class="active"><a href="#">1</a></li>
                    <li class="next disabled"><a href="#">Հաջորդ</a></li>
                  </ul>
                </div>
              </div>
            </div>
          </section>
        </div>
      </div>
    </div>

    <div class="modal fade" id="ordersModal" role="dialog">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-body">
            <div><p>2026-07-03 17:20</p><p>3 / Ներսի սրահ <a target="_blank" href="reports-check.html">#97708</a> (2 հատ)</p></div>
            <hr>
            <div><p>2026-07-03 16:44</p><p>7 / Դրսի սրահ <a target="_blank" href="reports-check.html">#97711</a> (1 հատ)</p></div>
          </div>
        </div>
      </div>
    </div>

    <input type="hidden" id="startDate" value="2026-07-03 00:00:00">
    <input type="hidden" id="endDate" value="2026-07-03 23:59:59">
    <input type="hidden" class="excelData" value='[{"lineName":"Ծրագրի անվանում","lineValue":"Smart Rest"},{"lineName":"Ծրագրի բաժին","lineValue":"Հաշվետվություններ / Առևտուր"}]'>
  </div>`;
}

function reportsComboContent() {
  const columns = [
    { key: 'comboTitle', label: 'Փաթեթ', filter: 'select' },
    { key: 'componentTitle', label: 'Բաղադրիչ', filter: 'select' },
    { key: 'quantity', label: 'Քանակ', filter: 'number' },
    { key: 'costPrice', label: 'Ինքնարժեք', filter: 'text' }
  ];
  const rows = [
    { comboTitle: 'Նախաճաշ Combo', componentTitle: 'Սուրճ', quantity: '2', costPrice: '800' },
    { comboTitle: 'Նախաճաշ Combo', componentTitle: 'Կրուասան', quantity: '2', costPrice: '1400' },
    { comboTitle: 'Լանչ Combo', componentTitle: 'Կեսար աղցան', quantity: '1', costPrice: '1600' },
    { comboTitle: 'Լանչ Combo', componentTitle: 'Լիմոնադ', quantity: '2', costPrice: '900' },
    { comboTitle: 'Ընտանեկան Combo', componentTitle: 'Պիցցա Մարգարիտա', quantity: '1', costPrice: '2400' },
    { comboTitle: 'Ընտանեկան Combo', componentTitle: 'Կարտոֆիլ ֆրի', quantity: '3', costPrice: '1200' }
  ];

  const headerCells = columns.map(column => `<th>${column.label} <span class="sort-arrows">↕</span></th>`).join('');
  const filterCells = columns.map(column => {
    if (column.filter === 'select') {
      return `<td class="dropDown_type">
        <select class="form-control">
          <option value=""></option>
          <option>Նախաճաշ Combo</option>
          <option>Լանչ Combo</option>
          <option>Ընտանեկան Combo</option>
          <option>Սուրճ</option>
          <option>Կրուասան</option>
          <option>Կեսար աղցան</option>
          <option>Լիմոնադ</option>
          <option>Պիցցա Մարգարիտա</option>
          <option>Կարտոֆիլ ֆրի</option>
        </select>
      </td>`;
    }

    return `<td><input type="${column.filter}" class="form-control" placeholder="Փնտրել"></td>`;
  }).join('');
  const bodyRows = rows.map(row => `<tr>${columns.map(column => `<td>${row[column.key]}</td>`).join('')}</tr>`).join('');

  return `<div class="reports-combo-page">
    <div class="row">
      <div class="col-xs-12">
        <section class="panel reports-combo-panel">
          <div class="row reports-combo-filter-row">
            <form role="form" method="get">
              <div class="input-group input-large padding-left0 col-sm-6 col-xs-12 pull-right header_filter" data-date-format="yyyy/mm/dd" id="noPadL">
                <input type="text" class="form-control dpd1" value="2026-07-03" name="start">
                <span class="input-group-addon">ից</span>
                <input type="text" class="form-control dpd2" value="2026-07-03" name="end">
                <span class="input-group-btn">
                  <button class="btn btn-md btn-info padding5" type="submit">Ֆիլտրել</button>
                </span>
              </div>
            </form>
          </div>

          <div class="row reports-combo-actions">
            <div class="col-sm-6">
              <button type="button" class="btn btn-success all" value="group">Ցուցադրել ըստ փաթեթի</button>
              <button type="button" class="btn btn-primary reset">Վերականգնել</button>
            </div>
            <div class="col-sm-6 text-right">
              <button class="btn btn-primary excelButton">Excel <i class="fa fa-table"></i></button>
            </div>
          </div>

          <hr>

          <div class="panel-body">
            <div id="toolPager">
              <label>Ցուցադրել</label>
              <select class="form-control">
                <option>10</option>
                <option>25</option>
                <option>50</option>
                <option>100</option>
              </select>
              <span>գրառում</span>
            </div>

            <div class="grid-view">
              <table class="table table-striped table-bordered reports-combo-table" id="ComboGridTable">
                <thead>
                  <tr>${headerCells}</tr>
                  <tr class="filters">${filterCells}</tr>
                </thead>
                <tbody>${bodyRows}</tbody>
              </table>
            </div>

            <div class="row reports-combo-grid-bottom">
              <div class="col-sm-5">
                <div class="summary">Ցուցադրված է 1-ից 6-ը 6 գրառումից</div>
              </div>
              <div class="col-sm-7">
                <ul class="pagination">
                  <li class="prev disabled"><a href="#">Նախորդ</a></li>
                  <li class="active"><a href="#">1</a></li>
                  <li class="next disabled"><a href="#">Հաջորդ</a></li>
                </ul>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>
  </div>`;
}

function reportsIngredientsContent() {
  const hiddenColumns = ['', '', '', ''];
  const columns = [
    'Անվանում',
    'Քանակ',
    'Գինը'
  ];
  const allColumns = [...hiddenColumns, ...columns];
  const rows = [
    { title: 'Լոլիկ կգ', quantity: '8.250', value: '6187.50' },
    { title: 'Հավի միս կգ', quantity: '12.400', value: '27280.00' },
    { title: 'Կարտոֆիլ կգ', quantity: '18.000', value: '5400.00' },
    { title: 'Սուրճ հատ', quantity: '6.000', value: '7200.00' },
    { title: 'Ալյուր կգ', quantity: '4.500', value: '2025.00' },
    { title: 'Պանիր կգ', quantity: '3.200', value: '12800.00' }
  ];

  const headerCells = allColumns.map((column, index) => index < hiddenColumns.length
    ? '<th class="ingredients-hidden-col"></th>'
    : `<th>${column} <span class="sort-arrows">↕</span></th>`).join('');
  const searchCells = allColumns.map((_, index) => index < hiddenColumns.length
    ? '<td class="ingredients-hidden-col"><input type="text" class="form-control" placeholder="Փնտրել"></td>'
    : '<td><input type="text" class="form-control" placeholder="Փնտրել"></td>').join('');
  const bodyRows = rows.map(row => `<tr>
    <td class="ingredients-hidden-col"></td>
    <td class="ingredients-hidden-col"></td>
    <td class="ingredients-hidden-col"></td>
    <td class="ingredients-hidden-col"></td>
    <td>${row.title}</td>
    <td>${row.quantity}</td>
    <td>${row.value}</td>
  </tr>`).join('');

  return `<div class="reports-ingredients-page">
    <div class="row">
      <div class="col-xs-12">
        <section class="panel reports-ingredients-panel">
          <header class="panel-heading">
            <ul class="nav nav-tabs menuButtonsContainer">
              <li class="active"><a href="?type=all">Ընդհանուր</a></li>
              <li><a href="?type=deleted">Ջնջված</a></li>
            </ul>
          </header>

          <div class="form-group reports-ingredients-category">
            <select class="form-control" id="materialCategoryFilter">
              <option value="0" selected>Բոլորը</option>
              <option value="1">Բանջարեղեն</option>
              <option value="2">Միս</option>
              <option value="3">Բար</option>
              <option value="4">Խոհանոց</option>
            </select>
          </div>

          <div class="panel-body">
            <form method="get" class="ingredients-filter-form">
              <div class="row m-bot15">
                <div class="input-group input-large col-sm-6 col-xs-12 m-bot15 header_filter" data-date-format="yyyy/mm/dd" id="ingredientsDateRange">
                  <input type="text" class="form-control dpd1" value="2026-07-03" name="datepicker_start_date">
                  <span class="input-group-addon inputsDivider">ից</span>
                  <input type="text" class="form-control dpd2" value="2026-07-03" name="datepicker_end_date">
                </div>

                <div class="input-group input-large padding-left0 col-sm-6 col-xs-12 header_filter header2">
                  <div class="input-group bootstrap-timepicker">
                    <span class="input-group-btn">
                      <button class="btn btn-default button-time-icon brn timeIcon1" type="button"><i class="icon-time"></i></button>
                    </span>
                    <input type="text" class="form-control timepicker-24 tp1 border-radius0" name="clock_start" value="00:00:00">
                  </div>
                  <span class="input-group-addon border-radius0 inputsDivider">ից</span>
                  <div class="input-group bootstrap-timepicker">
                    <input type="text" class="form-control timepicker-24 tp2 border-radius0" name="clock_end" value="23:59:59">
                    <span class="input-group-btn">
                      <button class="btn btn-default button-time-icon bln timeIcon2" type="button"><i class="icon-time"></i></button>
                    </span>
                  </div>
                  <span class="input-group-btn">
                    <button class="btn btn-md btn-info padding5 filterButton" type="submit" name="datetimepickerSubmit">Ֆիլտրել</button>
                  </span>
                </div>

                <div class="col-xs-12 input-group food_date_print">
                  <select class="form-control breakPointSelect" name="breakPointSelect">
                    <option>15.06.2026 23:48:57 - 03.07.2026 17:53:29</option>
                    <option>03.07.2026 09:00:00 - 03.07.2026 17:00:00</option>
                  </select>
                  <span class="input-group-btn buttonsLine">
                    <button class="btn btn-info smart-select padding5 showByShift" type="submit" name="breakPointSubmit">Ցուցադրել ըստ հերթափոխի</button>
                    <button id="ingredientsShowReport" class="btn btn-warning padding5 pull-right" type="button">Տալ հաշվետվություն</button>
                  </span>
                </div>
              </div>
            </form>

            <div class="row reports-ingredients-tools">
              <div class="col-sm-6">
                <button class="btn btn-warning m-r-10 printButton"><img src="assets/img/icons/printIcon.svg" alt="">Տպել</button>
                <button class="btn btn-primary excelButton"><img src="assets/img/icons/ExcelLogo.svg" alt="">Excel</button>
              </div>
              <div class="col-sm-6 text-right reports-ingredients-search">
                <label>Փնտրել</label>
                <input type="text" class="form-control" placeholder="">
              </div>
            </div>

            <div class="grid-view">
              <table class="table table-striped table-advance table-bordered table-hover reports-ingredients-table mytable ingredient_table" id="ingredientsTable">
                <thead>
                  <tr>${headerCells}</tr>
                  <tr class="column-search">${searchCells}</tr>
                </thead>
                <tbody>${bodyRows}</tbody>
                <tfoot>
                  <tr>
                    <th class="ingredients-hidden-col"></th>
                    <th class="ingredients-hidden-col"></th>
                    <th class="ingredients-hidden-col"></th>
                    <th class="ingredients-hidden-col"></th>
                    <th>Ընդհանուր</th>
                    <th class="total-sum">52</th>
                    <th class="total-sum">60893</th>
                  </tr>
                </tfoot>
              </table>
            </div>

            <div class="row reports-ingredients-grid-bottom">
              <div class="col-sm-5">
                <div class="dataTables_info">Ցուցադրված է 1-ից 6-ը 6 գրառումից</div>
              </div>
              <div class="col-sm-7">
                <div class="dataTables_paginate paging_bootstrap pagination">
                  <ul><li class="prev disabled"><a href="#">Նախորդ</a></li><li class="active"><a href="#">1</a></li><li class="next disabled"><a href="#">Հաջորդ</a></li></ul>
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>

    <input type="hidden" class="excelData" value='[{"lineName":"Ծրագրի անվանում","lineValue":"Smart Rest"},{"lineName":"Ծրագրի բաժին","lineValue":"Հաշվետվություններ / Հումքեր"}]'>
  </div>`;
}

function reportsMaterialGroupHistoryContent() {
  const columns = [
    { key: 'title', label: 'Անվանում', filter: 'text', footer: 'Ընդհանուր' },
    { key: 'quantity', label: 'Քանակ', filter: 'number', footer: '52.350' },
    { key: 'cost', label: 'Ինքնարժեք', filter: 'number', footer: '60893' },
    { key: 'documents', label: 'Փաստաթղթեր', filter: false, footer: '' }
  ];
  const rows = [
    { id: '31', title: 'Բանջարեղեն', quantity: '26.250', cost: '13512.50' },
    { id: '42', title: 'Միս', quantity: '12.400', cost: '27280.00' },
    { id: '55', title: 'Բար', quantity: '6.000', cost: '7200.00' },
    { id: '68', title: 'Խոհանոց', quantity: '7.700', cost: '12900.50' }
  ];

  const headerCells = columns.map(column => `<th class="${column.key === 'documents' ? 'material-group-documents-header' : ''}">${column.label} <span class="sort-arrows">↕</span></th>`).join('');
  const filterCells = columns.map(column => column.filter
    ? `<td><input type="${column.filter}" class="form-control" placeholder="Փնտրել"></td>`
    : '<td></td>').join('');
  const footerCells = columns.map(column => `<td>${column.footer}</td>`).join('');
  const bodyRows = rows.map(row => `<tr class="material-group-summary-row" data-material-id="${row.id}" role="button" tabindex="0" aria-expanded="false">
    <td>${row.title}</td>
    <td>${row.quantity}</td>
    <td>${row.cost}</td>
    <td class="text-center"><button class="btn btn-info btn-xs material-group-documents-btn" data-material-id="${row.id}" data-material-title="${row.title}"><i class="fa fa-files-o"></i> Փաստաթղթեր</button></td>
  </tr>
  <tr class="material-group-accordion-row material-group-accordion-row-hidden" data-material-id="${row.id}">
    <td colspan="4">
      <div class="material-group-accordion-wrapper">
        <p class="material-group-accordion-loader">Բեռնվում է...</p>
      </div>
    </td>
  </tr>`).join('');

  return `<div class="reports-material-group-history-page">
    <div class="row">
      <div class="col-xs-12">
        <section class="panel">
          <div class="panel-body">
            <form method="get" class="material-group-filter-form">
              <div class="row m-bot15">
                <div class="input-group input-large col-sm-6 col-xs-12 m-bot15 header_filter" data-date-format="yyyy/mm/dd" id="materialGroupDateRange">
                  <input type="text" class="form-control dpd1" value="2026-07-03" name="datepicker_start_date">
                  <span class="input-group-addon inputsDivider">ից</span>
                  <input type="text" class="form-control dpd2" value="2026-07-03" name="datepicker_end_date">
                </div>

                <div class="input-group input-large padding-left0 col-sm-6 col-xs-12 header_filter header2">
                  <div class="input-group bootstrap-timepicker">
                    <span class="input-group-btn">
                      <button class="btn btn-default button-time-icon brn timeIcon1" type="button"><i class="icon-time"></i></button>
                    </span>
                    <input type="text" class="form-control timepicker-24 tp1 border-radius0" name="clock_start" value="00:00:00">
                  </div>
                  <span class="input-group-addon border-radius0 inputsDivider">ից</span>
                  <div class="input-group bootstrap-timepicker">
                    <input type="text" class="form-control timepicker-24 tp2 border-radius0" name="clock_end" value="23:59:59">
                    <span class="input-group-btn">
                      <button class="btn btn-default button-time-icon bln timeIcon2" type="button"><i class="icon-time"></i></button>
                    </span>
                  </div>
                  <span class="input-group-btn">
                    <button class="btn btn-md btn-info padding5 filterButton" type="submit" name="datetimepickerSubmit">Ֆիլտրել</button>
                  </span>
                </div>

                <div class="col-xs-12 input-group food_date_print">
                  <select class="form-control breakPointSelect" name="breakPointSelect">
                    <option>15.06.2026 23:48:57 - 03.07.2026 17:53:29</option>
                    <option>03.07.2026 09:00:00 - 03.07.2026 17:00:00</option>
                  </select>
                  <span class="input-group-btn buttonsLine">
                    <button class="btn btn-info smart-select padding5 showByShift" type="submit" name="breakPointSubmit">Ցուցադրել ըստ հերթափոխի</button>
                    <button id="materialGroupShowReport" class="btn btn-warning padding5 pull-right" type="button">Տալ հաշվետվություն</button>
                  </span>
                </div>
              </div>
            </form>

            <div class="material-group-excel-toolbar">
              <button class="btn btn-primary"><img src="assets/img/icons/ExcelLogo.svg" alt="">Excel</button>
            </div>

            <div class="material-group-table-container">
              <div id="toolPager">
                <label>Ցուցադրել</label>
                <select class="form-control">
                  <option>20</option>
                  <option>50</option>
                  <option>100</option>
                </select>
                <span>գրառում</span>
              </div>

              <div class="grid-view">
                <table class="table table-striped table-bordered material-group-history-table" id="TableHistoryGridTable">
                  <thead>
                    <tr class="material-group-header-row">${headerCells}</tr>
                    <tr class="filters material-group-filter-row">${filterCells}</tr>
                  </thead>
                  <tbody>${bodyRows}</tbody>
                  <tfoot>
                    <tr>${footerCells}</tr>
                  </tfoot>
                </table>
              </div>

              <div class="row material-group-grid-bottom">
                <div class="col-sm-5">
                  <div class="summary">Ցուցադրված է 1-ից 4-ը 4 գրառումից</div>
                </div>
                <div class="col-sm-7">
                  <ul class="pagination">
                    <li class="prev disabled"><a href="#">Նախորդ</a></li>
                    <li class="active"><a href="#">1</a></li>
                    <li class="next disabled"><a href="#">Հաջորդ</a></li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>

    <div class="modal fade material-group-documents-modal" id="materialGroupDocumentsModal" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <h4 class="modal-title">
              <span class="material-group-modal-title-main"><i class="fa fa-files-o"></i> Խմբի փաստաթղթեր</span>
              <small class="material-group-modal-subtitle">Բանջարեղեն</small>
            </h4>
          </div>
          <div class="modal-body">
            <table class="table table-bordered material-group-documents-table">
              <thead><tr><th>Փաստաթուղթ</th><th>Ամսաթիվ</th><th>Քանակ</th><th>Գումար</th></tr></thead>
              <tbody>
                <tr><td><a class="material-group-document-link" href="#">#135637</a></td><td>03.07.2026</td><td>8.250</td><td>6187.50</td></tr>
                <tr><td><a class="material-group-document-link" href="#">#135641</a></td><td>03.07.2026</td><td>18.000</td><td>5400.00</td></tr>
              </tbody>
            </table>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">Փակել</button>
          </div>
        </div>
      </div>
    </div>

    <input type="hidden" value="datepicker_start_date=2026-07-03&datepicker_end_date=2026-07-03" id="urlParameters">
    <input type="hidden" id="translationObjectInput" value='{}'>
    <input type="hidden" value="2026-07-03 00:00:00" id="startDate">
    <input type="hidden" value="2026-07-03 23:59:59" id="endDate">
  </div>`;
}

function reportsLogsContent() {
  const rows = [
    { date: '2026-07-03 17:53:29', user: 'smart', branch: 'Կենտրոն', category: 'Պատվերներ', action: 'Թարմացվել է պատվեր #97708' },
    { date: '2026-07-03 16:41:12', user: 'Arpi', branch: 'Դրսի սրահ', category: 'Սեղաններ', action: 'Փոխվել է սեղանի կարգավիճակը' },
    { date: '2026-07-03 15:28:44', user: 'admin', branch: 'Երկրորդ հարկ', category: 'Դրամարկղ', action: 'Մուտքագրվել է կանխիկ վճարում' },
    { date: '2026-07-03 14:12:06', user: 'Mariam', branch: 'Առաջին հարկ', category: 'Ապրանքներ', action: 'Փոփոխվել է ապրանքի գինը' },
    { date: '2026-07-03 12:07:51', user: 'smart', branch: 'Կենտրոն', category: 'Պահեստ', action: 'Ստեղծվել է տեղափոխման փաստաթուղթ' },
    { date: '2026-07-03 10:35:18', user: 'Arpi', branch: 'Բար', category: 'Հաշվետվություններ', action: 'Ներբեռնվել է Excel հաշվետվություն' }
  ];

  const bodyRows = rows.map((row, index) => `<tr>
    <td class="logs-hidden-col">${rows.length - index}</td>
    <td class="logs-hidden-col">${row.user}</td>
    <td class="logs-hidden-col">${row.branch}</td>
    <td class="logs-hidden-col">${row.category}</td>
    <td>${row.date}</td>
    <td>${row.user}</td>
    <td>${row.branch}</td>
    <td>${row.category}</td>
    <td>${row.action}</td>
  </tr>`).join('');

  return `<div class="reports-logs-page">
    <div class="row">
      <div class="col-xs-12">
        <section class="panel">
          <div class="panel-body">
            <div class="col-xs-12 clearfix reports-logs-filter-wrap">
              <div class="row">
                <form role="form" method="get" class="reports-logs-filter-form">
                  <div class="input-group input-large padding-left0 col-sm-6 col-xs-12 pull-right header_filter" data-date-format="yyyy/mm/dd" id="reportsLogsDateRange">
                    <input type="text" class="form-control dpd1" value="2026-07-03" name="start_date">
                    <span class="input-group-addon inputsDivider">ից</span>
                    <input type="text" class="form-control dpd2" value="2026-07-03" name="end_date">
                    <span class="input-group-btn">
                      <button class="btn btn-md btn-info padding5 filterButton" type="submit">Ֆիլտրել</button>
                    </span>
                  </div>
                </form>
              </div>
            </div>

            <div class="row reports-logs-tools">
              <div class="col-sm-6">
                <button class="btn btn-warning m-r-10 printButton"><img src="assets/img/icons/printIcon.svg" alt=""> Տպել</button>
                <button class="btn btn-primary excelButton"><img src="assets/img/icons/ExcelLogo.svg" alt=""> Excel</button>
              </div>
              <div class="col-sm-6">
                <div class="reports-logs-search">
                  <label>Փնտրել:</label>
                  <input type="search" class="form-control" aria-controls="reportsLogsTable">
                </div>
              </div>
            </div>

            <div class="grid-view">
              <table class="table table-striped table-advance table-bordered table-hover mytable reports-logs-table" id="reportsLogsTable">
                <thead>
                  <tr>
                    <th class="logs-hidden-col"></th>
                    <th class="logs-hidden-col"></th>
                    <th class="logs-hidden-col"></th>
                    <th class="logs-hidden-col"></th>
                    <th>Ամսաթիվ <span class="sort-arrows">↕</span></th>
                    <th>Օգտատեր <span class="sort-arrows">↕</span></th>
                    <th>Մասնաճյուղ <span class="sort-arrows">↕</span></th>
                    <th>Բաժին <span class="sort-arrows">↕</span></th>
                    <th>Գործողություն <span class="sort-arrows">↕</span></th>
                  </tr>
                  <tr class="column-search">
                    <td class="logs-hidden-col"><input type="text" class="form-control" placeholder="Փնտրել"></td>
                    <td class="logs-hidden-col"><input type="text" class="form-control" placeholder="Փնտրել"></td>
                    <td class="logs-hidden-col"><input type="text" class="form-control" placeholder="Փնտրել"></td>
                    <td class="logs-hidden-col"><input type="text" class="form-control" placeholder="Փնտրել"></td>
                    <td><input type="text" class="form-control" placeholder="Փնտրել"></td>
                    <td><input type="text" class="form-control" placeholder="Փնտրել"></td>
                    <td><input type="text" class="form-control" placeholder="Փնտրել"></td>
                    <td><input type="text" class="form-control" placeholder="Փնտրել"></td>
                    <td><input type="text" class="form-control" placeholder="Փնտրել"></td>
                  </tr>
                </thead>
                <tbody>${bodyRows}</tbody>
              </table>
            </div>

            <div class="row reports-logs-grid-bottom">
              <div class="col-sm-5">
                <div class="dataTables_info">Ցուցադրված է 1-ից 6-ը 6 գրառումից</div>
              </div>
              <div class="col-sm-7">
                <div class="dataTables_paginate paging_bootstrap pagination">
                  <ul><li class="prev disabled"><a href="#">Նախորդ</a></li><li class="active"><a href="#">1</a></li><li class="next disabled"><a href="#">Հաջորդ</a></li></ul>
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>

    <input type="hidden" class="excelData" value='[{"lineName":"Ծրագրի անվանում","lineValue":"Smart Rest"},{"lineName":"Ծրագրի բաժին","lineValue":"Հաշվետվություններ / Գործողության պատմություն"}]'>
  </div>`;
}

function analysisPlanningContent() {
  const plans = [
    { id: 18, label: '2026-06-01 - 2026-06-30', selected: true },
    { id: 17, label: '2026-05-01 - 2026-05-31', selected: false },
    { id: 16, label: '2026-04-01 - 2026-04-30', selected: false }
  ];
  const planOptions = plans.map(plan => `<option value="${plan.id}"${plan.selected ? ' selected' : ''}>${plan.label}</option>`).join('');

  return `<div class="analysis-planning-page">
    <div class="row">
      <div class="col-xs-12 clearfix analysis-planning-action">
        <button class="btn btn-success m-bot15 pull-left" data-toggle="modal" data-target="#addModal"><i class="icon-plus"></i> Պլանավորել</button>
      </div>

      <div class="col-md-12">
        <div class="filter-container header_filter planning_filter analysis-planning-filter">
          <div class="col-md-4 analysis-planning-select">
            <select class="form-control select2" id="selectPlan">
              <option>Select planning</option>
              ${planOptions}
            </select>
          </div>

          <div class="col-md-6 analysis-planning-date">
            <div class="input-group input-large padding-left0" data-date-format="yyyy/mm/dd" id="noPadL">
              <input type="text" class="form-control dpd1" value="2026-07-03" name="start_date">
              <span class="input-group-addon inputsDivider">ից</span>
              <input type="text" class="form-control dpd2" value="2026-07-03" name="end_date">
            </div>
          </div>

          <button class="btn btn-primary filter-planning pull-right padding5">Ֆիլտրել</button>
        </div>

        <table class="table analysis-planning-table">
          <thead>
            <tr>
              <th class="border-right-bolder"></th>
              <th class="border-right-bolder" colspan="2">2026-06-01 - 2026-06-30</th>
              <th>2026-07-03 - 2026-07-03</th>
            </tr>
            <tr>
              <th class="border-right-bolder"></th>
              <th class="border-right">Planning</th>
              <th class="border-right-bolder">Fact</th>
              <th>Comparison</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <th class="border-right-bolder">Purchase</th>
              <td class="border-right">840000</td>
              <td class="border-right-bolder">812450</td>
              <td>26500</td>
            </tr>
            <tr>
              <th class="border-right-bolder">Sales</th>
              <td class="border-right">1450000</td>
              <td class="border-right-bolder">1512800</td>
              <td>48200</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div id="addModal" class="modal fade form-horizontal addModal analysis-planning-modal" role="dialog">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <h4 class="modal-title">Plan</h4>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label>Purchase</label>
              <input type="number" class="form-control" id="procurement">
            </div>
            <div class="form-group">
              <label>Sales</label>
              <input type="number" class="form-control" id="sales">
            </div>
            <div class="form-group">
              <div class="input-group input-large padding-left0" data-date-format="yyyy/mm/dd" id="planningModalDateRange">
                <input readonly type="text" class="form-control dpd1" value="2026-07-03" id="start">
                <span class="input-group-addon">ից</span>
                <input readonly type="text" class="form-control dpd2" value="2026-07-03" id="end">
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button>
            <button class="btn btn-success pull-right" id="create-planning" type="button">Հաստատել</button>
          </div>
        </div>
      </div>
    </div>

    <input type="hidden" id="errorPlaning" value="Ընտրեք պլանավորում">
    <input type="hidden" id="csrfToken" value="static-token">
  </div>`;
}

function analysisTopPassiveContent() {
  const rows = [
    { quantity: 128, product: 'Լատե', group: 'Սուրճ', place: 'Բար', branch: 'Կենտրոն' },
    { quantity: 116, product: 'Կապուչինո', group: 'Սուրճ', place: 'Բար', branch: 'Կենտրոն' },
    { quantity: 94, product: 'Հավով աղցան', group: 'Աղցաններ', place: 'Խոհանոց', branch: 'Առաջին հարկ' },
    { quantity: 88, product: 'Բուրգեր', group: 'Արագ սնունդ', place: 'Խոհանոց', branch: 'Դրսի սրահ' },
    { quantity: 76, product: 'Ֆրի', group: 'Խորտիկներ', place: 'Խոհանոց', branch: 'Դրսի սրահ' },
    { quantity: 63, product: 'Թեյ', group: 'Թեյեր', place: 'Բար', branch: 'Երկրորդ հարկ' },
    { quantity: 59, product: 'Պիցցա Մարգարիտա', group: 'Պիցցա', place: 'Խոհանոց', branch: 'Կենտրոն' },
    { quantity: 51, product: 'Սպագետի', group: 'Պաստա', place: 'Խոհանոց', branch: 'Առաջին հարկ' }
  ];

  const bodyRows = rows.map((row, index) => `<tr>
    <td class="top-passive-hidden-col">${index + 1}</td>
    <td class="top-passive-hidden-col">${row.product}</td>
    <td class="top-passive-hidden-col">${row.group}</td>
    <td class="top-passive-hidden-col">${row.place}</td>
    <td>${row.quantity}</td>
    <td>${row.product}</td>
    <td>${row.group}</td>
    <td>${row.place}</td>
    <td>${row.branch}</td>
  </tr>`).join('');

  return `<div class="analysis-top-passive-page">
    <div class="row">
      <div class="panel padd15 analysis-top-passive-tabs-panel">
        <header class="panel-heading">
          <ul class="nav nav-tabs menuButtonsContainer">
            <li class="active"><a href="analysis-top-passive.html">Թոփ վաճառք</a></li>
            <li><a href="analysis-top-passive.html#passive">Պասիվ վաճառք</a></li>
          </ul>
        </header>
      </div>

      <div class="col-xs-12 clearfix header_filter analysis-top-passive-filter-wrap">
        <form role="form" method="get" class="form_btn_pad analysis-top-passive-filter-form">
          <div class="input-group input-large padding-left0 col-sm-8 col-xs-12 pull-right" data-date-format="yyyy/mm/dd" id="noPadL">
            <input type="number" class="form-control quantityInput" name="qty" value="10">
            <span class="input-group-addon inputsDivider">հատ ապրանք</span>
            <input type="text" class="form-control dpd1" value="2026-06-03" name="start_date">
            <span class="input-group-addon inputsDivider">ից</span>
            <input type="text" class="form-control dpd2" value="2026-07-03" name="end_date">
            <span class="input-group-btn">
              <button class="btn btn-md btn-info padding5 filterButton" type="submit">Ֆիլտրել</button>
            </span>
          </div>
        </form>
      </div>

      <div class="panel analysis-top-passive-table-panel">
        <div class="panel-body padd15">
          <div class="row analysis-top-passive-tools">
            <div class="col-sm-6">
              <button class="btn btn-warning m-r-10 printButton"><img src="assets/img/icons/printIcon.svg" alt=""> Տպել</button>
              <button class="btn btn-primary excelButton"><img src="assets/img/icons/ExcelLogo.svg" alt=""> Excel</button>
            </div>
            <div class="col-sm-6">
              <div class="analysis-top-passive-search">
                <label>Փնտրել:</label>
                <input type="search" class="form-control" aria-controls="analysisTopPassiveTable">
              </div>
            </div>
          </div>

          <div class="grid-view">
            <table class="table table-bordered mytable analysis-top-passive-table" id="analysisTopPassiveTable">
              <thead>
                <tr>
                  <th class="top-passive-hidden-col"></th>
                  <th class="top-passive-hidden-col"></th>
                  <th class="top-passive-hidden-col"></th>
                  <th class="top-passive-hidden-col"></th>
                  <th>Quantity <span class="sort-arrows">↕</span></th>
                  <th>Product <span class="sort-arrows">↕</span></th>
                  <th>Menu group <span class="sort-arrows">↕</span></th>
                  <th>Menu place <span class="sort-arrows">↕</span></th>
                  <th>Branch <span class="sort-arrows">↕</span></th>
                </tr>
                <tr class="column-search">
                  <td class="top-passive-hidden-col"><input type="text" class="form-control" placeholder="Փնտրել"></td>
                  <td class="top-passive-hidden-col"><input type="text" class="form-control" placeholder="Փնտրել"></td>
                  <td class="top-passive-hidden-col"><input type="text" class="form-control" placeholder="Փնտրել"></td>
                  <td class="top-passive-hidden-col"><input type="text" class="form-control" placeholder="Փնտրել"></td>
                  <td><input type="text" class="form-control" placeholder="Փնտրել"></td>
                  <td><input type="text" class="form-control" placeholder="Փնտրել"></td>
                  <td><input type="text" class="form-control" placeholder="Փնտրել"></td>
                  <td><input type="text" class="form-control" placeholder="Փնտրել"></td>
                  <td><input type="text" class="form-control" placeholder="Փնտրել"></td>
                </tr>
              </thead>
              <tbody>${bodyRows}</tbody>
            </table>
          </div>

          <div class="row analysis-top-passive-grid-bottom">
            <div class="col-sm-5">
              <div class="dataTables_info">Ցուցադրված է 1-ից 8-ը 8 գրառումից</div>
            </div>
            <div class="col-sm-7">
              <div class="dataTables_paginate paging_bootstrap pagination">
                <ul><li class="prev disabled"><a href="#">Նախորդ</a></li><li class="active"><a href="#">1</a></li><li class="next disabled"><a href="#">Հաջորդ</a></li></ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <input type="hidden" id="csrfToken" name="csrf_token" value="static-token">
    <input type="hidden" id="sessionLang" value="hy">
    <input type="hidden" class="excelData" value='[{"lineName":"Ծրագրի անվանում","lineValue":"Smart Rest"},{"lineName":"Ծրագրի բաժին","lineValue":"Վերլուծություն / Թոփ/պասիվ վաճառք"}]'>
  </div>`;
}

function analysisOrderStatisticsContent() {
  const ranges = [
    { id: 1, label: '0 - 5000' },
    { id: 2, label: '5000 - 10000' },
    { id: 3, label: '10000 - 20000' },
    { id: 4, label: '20000 - ∞' }
  ];

  const stats = [
    { range: '0 - 5000', orders: 34, percent: '28%', cost: '68000', income: '142500', average: '4191', customers: 46 },
    { range: '5000 - 10000', orders: 51, percent: '42%', cost: '184300', income: '391200', average: '7670', customers: 73 },
    { range: '10000 - 20000', orders: 27, percent: '22%', cost: '213100', income: '401500', average: '14870', customers: 39 },
    { range: '20000 - ∞', orders: 10, percent: '8%', cost: '158000', income: '287900', average: '28790', customers: 18 }
  ];

  const rangeRows = ranges.map(range => `<tr>
    <td>${range.label}</td>
    <td>
      <button type="button" data-id="${range.id}" class="btn btn-danger btn-xs btn_delete inTableIconButton">
        <img src="assets/img/icons/trash.svg" alt="">
      </button>
    </td>
  </tr>`).join('');

  const statsRows = stats.map(row => `<tr>
    <td>${row.range}</td>
    <td>${row.orders}</td>
    <td>${row.percent}</td>
    <td>${row.cost}</td>
    <td>${row.income}</td>
    <td>${row.average}</td>
    <td>${row.customers}</td>
  </tr>`).join('');

  return `<div class="analysis-order-statistics-page">
    <input type="hidden" value="#" id="defaultDbURL">
    <div class="row">
      <div class="col-lg-12 pad_320">
        <section class="panel analysis-order-statistics-panel">
          <div class="col-xs-12 clearfix filter-container analysis-order-statistics-filter">
            <div class="row first">
              <div class="price-range">
                <input type="text" class="form-control price_from" name="price_from" value="0">
                <span class="input-group-addon from-price inputsDivider">ից</span>
                <input type="text" class="form-control price_to range" name="price_to" value="5000">
                <button class="btn btn-sm btn-success add-price-range" type="button"><i class="icon-plus"></i></button>
              </div>

              <div class="input-group input-large padding-left0 col-sm-6 col-xs-12 pull-right header_filter" data-date-format="yyyy/mm/dd" id="noPadL">
                <input type="text" class="form-control dpd1" value="2026-07-03" name="start">
                <span class="input-group-addon inputsDivider">ից</span>
                <input type="text" class="form-control dpd2" value="2026-07-03" name="end">
                <span class="input-group-btn">
                  <button class="btn btn-md btn-info approve-range padding5 filterButton" value="approveRange" type="submit">Հաստատել</button>
                </span>
              </div>
            </div>

            <div class="table-price-range">
              <table class="table table-bordered table-inbox subExpenseTable analysis-price-range-table">
                <thead>
                  <tr>
                    <th>Գումարային միջակայք</th>
                    <th><i class="icon-trash"></i></th>
                  </tr>
                </thead>
                <tbody>${rangeRows}</tbody>
              </table>
            </div>
          </div>

          <div class="panel analysis-order-statistics-result-panel">
            <div class="panel-body padd15">
              <table class="table table-bordered analysis-order-statistics-table">
                <thead>
                  <tr>
                    <th>Միջակայք</th>
                    <th>Պատվերների քանակ</th>
                    <th>Percent</th>
                    <th>Ինքնարժեք</th>
                    <th>Եկամուտ</th>
                    <th>Միջին հաշիվ</th>
                    <th>Հաճախորդների քանակ</th>
                  </tr>
                </thead>
                <tbody>${statsRows}</tbody>
              </table>
            </div>
          </div>
        </section>
      </div>
    </div>

    <input type="hidden" id="csrfToken" name="csrf_token" value="static-token">
  </div>`;
}

function analysisSalesStatisticsContent() {
  const rows = [
    { type: 'Առավելագույն', date: '2026-06-22', sum: '458200', cost: '213400', income: '244800' },
    { type: 'Նվազագույն', date: '2026-06-09', sum: '84200', cost: '39100', income: '45100' },
    { type: 'Միջին', date: '2026-06-03 - 2026-07-03', sum: '247850', cost: '112600', income: '135250' }
  ];
  const menuRows = [
    { menu: 'Սուրճ', amount: '128400', cost: '48200', income: '80200' },
    { menu: 'Խոհանոց', amount: '211600', cost: '112300', income: '99300' },
    { menu: 'Բար', amount: '118200', cost: '52900', income: '65300' }
  ];

  const bodyRows = rows.map(row => `<tr>
    <th>${row.type}</th>
    <td>${row.date}</td>
    <td>${row.sum}</td>
    <td>${row.cost}</td>
    <td>${row.income}</td>
    <td>
      <button data-id="${row.date}" class="btn btn-info openStatsByEachMenuModalButton" data-toggle="modal" data-target="#openStatsByEachMenuModal">
        <i class="icon-info-sign"></i>
      </button>
    </td>
  </tr>`).join('');

  const modalRows = menuRows.map(row => `<tr>
    <td></td>
    <td>${row.menu}</td>
    <td>${row.amount}</td>
    <td>${row.cost}</td>
    <td>${row.income}</td>
  </tr>`).join('');

  return `<div class="analysis-sales-statistics-page">
    <div class="row">
      <div class="col-xs-12 clearfix header_filter analysis-sales-statistics-filter-wrap">
        <form role="form" method="get" class="form_btn_pad analysis-sales-statistics-filter-form">
          <div class="input-group input-large padding-left0 col-sm-8 col-xs-12" data-date-format="yyyy/mm/dd" id="noPadL">
            <input type="text" class="form-control dpd1" value="2026-06-03" name="start_date">
            <span class="input-group-addon inputsDivider">ից</span>
            <input type="text" class="form-control dpd2" value="2026-07-03" name="end_date">
            <span class="input-group-btn">
              <button class="btn btn-md btn-info padding5 filterButton" type="submit">Ֆիլտրել</button>
            </span>
          </div>
        </form>
      </div>

      <div class="panel analysis-sales-statistics-table-panel">
        <div class="panel-body padd15">
          <div class="row analysis-sales-statistics-tools">
            <div class="col-sm-6">
              <button class="btn btn-warning m-r-10 printButton">Տպել <i class="fa fa-print"></i></button>
              <button class="btn btn-primary excelButton"><img src="assets/img/icons/ExcelLogo.svg" alt="">Excel</button>
            </div>
            <div class="col-sm-6">
              <div class="analysis-sales-statistics-search">
                <label>Փնտրել:</label>
                <input type="search" class="form-control" aria-controls="minMaxSalesTable">
              </div>
            </div>
          </div>

          <div class="grid-view">
            <table class="table table-bordered minMaxSalesTable analysis-sales-statistics-table" id="minMaxSalesTable">
              <thead>
                <tr>
                  <th></th>
                  <th>date <span class="sort-arrows">↕</span></th>
                  <th>sum <span class="sort-arrows">↕</span></th>
                  <th>cost_sum <span class="sort-arrows">↕</span></th>
                  <th>income <span class="sort-arrows">↕</span></th>
                  <th><i class="fa fa-cog"></i></th>
                </tr>
                <tr class="column-search">
                  <td><input type="text" class="form-control" placeholder="Փնտրել"></td>
                  <td><input type="text" class="form-control" placeholder="Փնտրել"></td>
                  <td><input type="text" class="form-control" placeholder="Փնտրել"></td>
                  <td><input type="text" class="form-control" placeholder="Փնտրել"></td>
                  <td><input type="text" class="form-control" placeholder="Փնտրել"></td>
                  <td></td>
                </tr>
              </thead>
              <tbody>${bodyRows}</tbody>
            </table>
          </div>

          <div class="row analysis-sales-statistics-grid-bottom">
            <div class="col-sm-5">
              <div class="dataTables_info">Ցուցադրված է 1-ից 3-ը 3 գրառումից</div>
            </div>
            <div class="col-sm-7">
              <div class="dataTables_paginate paging_bootstrap pagination">
                <ul><li class="prev disabled"><a href="#">Նախորդ</a></li><li class="active"><a href="#">1</a></li><li class="next disabled"><a href="#">Հաջորդ</a></li></ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade bd-example-modal-xl analysis-sales-statistics-modal" tabindex="-1" role="dialog" aria-hidden="true" id="openStatsByEachMenuModal">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="analysis-sales-statistics-modal-table">
            <table class="table">
              <thead>
                <tr>
                  <th class="border-right-bolder"></th>
                  <th class="border-right">menu</th>
                  <th class="border-right-bolder">amount</th>
                  <th class="border-right-bolder">cost_sum</th>
                  <th class="border-right-bolder">income</th>
                </tr>
              </thead>
              <tbody id="menuData">${modalRows}</tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <input type="hidden" id="csrfToken" name="csrf_token" value="static-token">
    <input type="hidden" id="sessionLang" value="hy">
    <input type="hidden" class="excelData" value='[{"lineName":"Ծրագրի անվանում","lineValue":"Smart Rest"},{"lineName":"Ծրագրի բաժին","lineValue":"Վերլուծություն / Վաճառքի ստատիստիկա"}]'>
  </div>`;
}

function cashContent() {
  const cashboxes = ['Ընդհանուր', 'Տերմինալ', 'Կանխիկ', 'Բանկային', 'IDRAM', 'Մատակարար', 'InecoPay', 'Telcell'];
  const historyRows = [
    { id: 90631, date: '2026-06-23 11:04:16', cashbox: 'Տերմինալ', enter: 500, exit: 0, balance: -2368326, author: 'smart', comment: 'Արափ ներմուծում\n#97708', receipt: '#97708', transaction: '', type: '', sub: '' },
    { id: 90630, date: '2026-06-16 15:46:16', cashbox: 'Բանկային', enter: 1550, exit: 0, balance: 9232494, author: 'Arpi', comment: 'Երկրորդ հարկ/4\nԿտրոն#97706', receipt: '#97706', transaction: '', type: '', sub: '' },
    { id: 90629, date: '2026-06-16 15:45:00', cashbox: 'Բանկային', enter: 3650, exit: 0, balance: 9230944, author: 'Arpi', comment: 'Դրսի սրահ/5\nԿտրոն#97703', receipt: '#97703', transaction: '', type: '', sub: '' },
    { id: 90628, date: '2026-06-16 15:27:23', cashbox: 'Բանկային', enter: 14650, exit: 0, balance: 9227294, author: 'Arpi', comment: 'Դրսի սրահ/12\nԿտրոն#97699', receipt: '#97699', transaction: '', type: '', sub: '' },
    { id: 90627, date: '2026-06-16 15:23:58', cashbox: 'Բանկային', enter: 8250, exit: 0, balance: 9212644, author: 'Arpi', comment: 'Դրսի սրահ/3\nԿտրոն#97697', receipt: '#97697', transaction: '', type: '', sub: '' },
    { id: 90626, date: '2026-06-16 15:20:15', cashbox: 'Բանկային', enter: 4950, exit: 0, balance: 9204394, author: 'Arpi', comment: 'Դրսի սրահ/2\nCheck#97696', receipt: '#97696', transaction: '', type: '', sub: '' },
    { id: 90625, date: '2026-06-16 15:16:21', cashbox: 'Բանկային', enter: 3950, exit: 0, balance: 9199444, author: 'Arpi', comment: 'Երկրորդ հարկ/5\nԿտրոն#97701', receipt: '#97701', transaction: '', type: '', sub: '' },
    { id: 90624, date: '2026-06-16 15:06:37', cashbox: 'Կանխիկ', enter: 0, exit: 6342, balance: 503375, author: 'Tovmasyan', comment: 'Փաստաթուղթ #135637\n15.06.26 Շուկա', receipt: '#135637', transaction: '', type: '', sub: '' },
    { id: 90623, date: '2026-06-16 15:04:41', cashbox: 'Բանկային', enter: 2200, exit: 0, balance: 9195494, author: 'Arpi', comment: 'Երկրորդ հարկ/1\nԿտրոն#97700', receipt: '#97700', transaction: '', type: '', sub: '' }
  ];

  const tabs = cashboxes.map((item, index) => `<li class="${index === 0 ? 'active' : ''}">
    <a href="${index === 0 ? '?cash=all' : `?cash=${index}`}">${item}</a>
  </li>`).join('');

  const rows = historyRows.map(row => `<tr>
    <td>${row.id}</td>
    <td>${row.date}</td>
    <td>${row.cashbox}</td>
    <td class="text-success">${row.enter || 0}</td>
    <td class="text-danger">${row.exit || 0}</td>
    <td>${row.balance}</td>
    <td>${row.author}</td>
    <td>${row.comment.replace(/\n/g, '<br>')}</td>
    <td>${row.receipt ? `<a class="text-primary" href="reports-check.html">${row.receipt}</a>` : ''}</td>
    <td>${row.transaction ? `<a class="text-primary" href="#">${row.transaction}</a>` : ''}</td>
    <td>${row.type}</td>
    <td>${row.sub}</td>
    <td class="cog_btns_td">
      <div class="flexBtns">
        <a href="#" class="print-row btn btn-xs btn-default"><i class="icon-print"></i></a>
        <a href="#cashEditModal" data-toggle="modal" class="btn btn-xs btn-warning editCacheBox"><i class="fa fa-edit"></i></a>
      </div>
    </td>
  </tr>`).join('');

  return `<div class="cash-page">
    <div class="row">
      <div class="col-xs-12 col-sm-4">
        <div class="state-overview">
          <section class="panel">
            <div class="symbol green"><i class="icon-tags"></i></div>
            <div class="value"><h1>68</h1><p>Կտրոնների քանակ</p></div>
          </section>
        </div>
      </div>
      <div class="col-xs-12 col-sm-4">
        <div class="state-overview">
          <section class="panel">
            <div class="symbol yellow"><i class="fa fa-shopping-cart" aria-hidden="true"></i></div>
            <div class="value"><h1>334350</h1><p>Մուտք վաճառքից</p></div>
          </section>
        </div>
      </div>
      <div class="col-xs-12 col-sm-4">
        <div class="state-overview">
          <section class="panel">
            <div class="symbol terques"><i class="icon-money"></i></div>
            <div class="value"><h1>8365202</h1><p>Ներկա մնացորդ</p></div>
          </section>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-xs-12">
        <div class="btn-group btn-group-justified m-bot15 header_Btns_cash">
          <a type="button" class="btn btn-success" id="cashInModalBtn" data-toggle="modal" data-target="#cashInModal">
            <i class="fa fa-plus"></i> Գումարի մուտք
          </a>
          <a type="button" class="btn btn-danger" id="addCashOutModal" data-toggle="modal" data-target="#cashOutModal">
            <i class="fa fa-minus"></i> Գումարի ելք
          </a>
          <a type="button" class="btn btn-info" data-toggle="modal" data-target="#transferModal">
            ↔ Տրանսֆեր
          </a>
          <a type="button" class="hidden btn btn-info" data-toggle="modal" data-target="#cardModal">
            <i class="glyphicon glyphicon-credit-card"></i> Լիցքավորել քարտ
          </a>
        </div>
      </div>
    </div>

    <section class="panel">
      <header class="panel-heading">
        <ul class="nav nav-tabs menuButtonsContainer">${tabs}</ul>
      </header>
      <div class="panel-body cash_table_body">
        <form method="get" class="cash-filter-form">
          <div class="row">
            <div class="input-group input-large col-md-6 col-sm-12 col-xs-12 m-bot15 header_filter cash-date-filter" data-date-format="yyyy/mm/dd" id="cashDateRange">
              <input type="text" class="form-control dpd1" value="2026-07-03" name="datepicker_start_date">
              <span class="input-group-addon inputsDivider">ից</span>
              <input type="text" class="form-control dpd2" value="2026-07-03" name="datepicker_end_date">
            </div>

            <div class="input-group input-large padding-left0 col-md-5 col-sm-10 col-xs-12 header_filter cash-time-filter">
              <div class="input-group bootstrap-timepicker">
                <span class="input-group-btn">
                  <button class="btn btn-default button-time-icon brn timeIcon1" type="button"><i class="icon-time"></i></button>
                </span>
                <input type="text" class="form-control timepicker-24 tp1 border-radius0" name="clock_start" value="00:00:00">
              </div>
              <span class="input-group-addon border-radius0 inputsDivider">ից</span>
              <div class="input-group bootstrap-timepicker">
                <input type="text" class="form-control timepicker-24 tp2 border-radius0" name="clock_end" value="23:59:59">
                <span class="input-group-btn">
                  <button class="btn btn-default button-time-icon bln timeIcon2" type="button"><i class="icon-time"></i></button>
                </span>
              </div>
            </div>

            <div class="col-md-1 col-sm-2 col-xs-12 cash-filter-button-col">
              <button class="btn btn-md btn-danger padding5 cash-filter-button" type="submit" name="datetimepickerSubmit">Ֆիլտրել</button>
            </div>

            <div class="col-xs-12 input-group food_date_print">
              <div class="type_of_charge">
                <select class="form-control" name="type">
                  <option value="all">Ծախսի տեսակ</option>
                  <option>Վաճառք</option>
                  <option>Ծախս</option>
                  <option>Մուտք</option>
                  <option>Տրանսֆեր</option>
                </select>
              </div>
              <select class="form-control" name="breakPointSelect">
                <option>15.06.2026 23:48:57 - 03.07.2026 15:41:44</option>
                <option>03.07.2026 00:00:00 - 03.07.2026 23:59:59</option>
                <option>02.07.2026 00:00:00 - 02.07.2026 23:59:59</option>
              </select>
              <span class="input-group-btn">
                <button class="btn btn-default smart-select padding5" type="submit" name="breakPointSubmit">Ցուցադրել ըստ հերթափոխի</button>
              </span>
            </div>
          </div>
        </form>

        <div class="row cash-grid-tools">
          <div class="col-md-8">
            <select class="form-control page-size-select">
              <option selected>30</option>
              <option>50</option>
              <option>100</option>
            </select>
          </div>
          <div class="col-md-3 filters globalSearchBlock text-right">
            <input type="text" id="cacheBoxSearchForm-globalSearch" class="form-control" name="CacheBoxSearchForm[globalSearch]" placeholder="Փնտրել">
          </div>
          <div class="col-md-1">
            <a class="dt-button buttons-excel buttons-html5 pull-right" tabindex="0">
              <span><button class="btn btn-primary">Excel <i class="fa fa-table" aria-hidden="true"></i></button></span>
            </a>
          </div>
        </div>

        <p class="cash-summary-line">Ցուցադրված են <b>1-ից 30-ը</b> ընդհանուր <b>91-ից</b>:</p>

        <div class="grid-view">
          <table class="table table-striped table-bordered" id="cacheBoxGridTable">
            <thead>
              <tr>
                <th>id</th>
                <th>Ամսաթիվ</th>
                <th>Դրամարկղ</th>
                <th>Մուտք</th>
                <th>Ելք</th>
                <th>Մնացորդ</th>
                <th>Ում կողմից</th>
                <th>Նպատակ</th>
                <th>Կտրոնի համար</th>
                <th>Գործարք</th>
                <th>Ծախսի տեսակ</th>
                <th>Ենթատեսակ</th>
                <th><i class="fa fa-cogs" aria-hidden="true"></i></th>
              </tr>
              <tr class="filters">
                <td><input class="form-control" value=""></td>
                <td><input class="form-control" value=""></td>
                <td><input class="form-control" value=""></td>
                <td><input class="form-control" value=""></td>
                <td><input class="form-control" value=""></td>
                <td><input class="form-control" value=""></td>
                <td><input class="form-control" value=""></td>
                <td><input class="form-control comment" value=""></td>
                <td><input class="form-control" value=""></td>
                <td><input class="form-control" value=""></td>
                <td><select class="form-control"><option></option><option>Վաճառք</option><option>Ծախս</option></select></td>
                <td><select class="form-control"><option></option><option>Խոհանոց</option><option>Քարտ</option></select></td>
                <td></td>
              </tr>
            </thead>
            <tbody>${rows}</tbody>
            <tfoot>
              <tr>
                <td colspan="3">Ընդհանուր</td>
                <td class="text-success">103600</td>
                <td class="text-danger">12000</td>
                <td colspan="8"></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </section>
  </div>
  ${cashModals()}`;
}

function cashModals() {
  const cashboxOptions = '<option value="">-- դրամարկղ --</option><option>Գլխավոր դրամարկղ</option><option>Բանկային</option><option>Առաքում</option>';
  const typeOptions = '<option>Վաճառք</option><option>Ծախս</option><option>Մուտք</option><option>Տրանսֆեր</option>';
  const cashFlowModal = (id, title, action, typeLabel) => `<div class="modal fade ${action}_modal" id="${id}" role="dialog" data-backdrop="static">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal">&times;</button>
          <h4 class="modal-title">${title}</h4>
        </div>
        <div class="modal-body form-horizontal">
          <div class="form-group"><label class="control-label col-sm-2">Գումարի չափը:</label><div class="col-sm-10"><input type="number" name="count" class="form-control count" required></div></div>
          <div class="form-group"><label class="control-label col-sm-2">Ում կողմից:</label><div class="col-sm-10"><textarea class="form-control author" name="author" required></textarea></div></div>
          <div class="form-group"><label class="control-label col-sm-2">Նպատակ:</label><div class="col-sm-10"><textarea class="form-control goal" name="goal" required></textarea></div></div>
          <div class="form-group"><label class="control-label col-sm-2">${typeLabel}:</label><div class="col-sm-10"><select class="form-control expence_type" name="expence_type">${typeOptions}</select></div></div>
          <div class="form-group"><label class="control-label col-sm-2">Ենթատեսակ:</label><div class="col-sm-10"><select class="form-control sub_expense" name="sub_expense"><option></option><option>Խոհանոց</option><option>Բար</option></select></div></div>
          <div class="form-group"><label class="control-label col-sm-2">Դրամարկղ:</label><div class="col-sm-10"><select class="form-control cashbox" name="cashbox">${cashboxOptions}</select></div></div>
          <div class="form-group"><div class="col-sm-8"><div class="checkbox"><label><input type="checkbox" class="deal_now" name="deal_now" value="1" checked> Գործարքը կատարել հիմա</label></div></div></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button>
          <button class="btn btn-success pull-right cashInSubmit" data-action="${action}">Հաստատել</button>
        </div>
      </div>
    </div>
  </div>`;

  return `${cashFlowModal('cashInModal', 'Մուտքագրել գումար', 'cashIn', 'Մուտքի տեսակ')}
  ${cashFlowModal('cashOutModal', 'Գումարի ելք', 'cashOut', 'Ելքի տեսակ')}
  <div id="transferModal" class="modal fade" role="dialog" data-backdrop="static">
    <div class="modal-dialog"><div class="modal-content"><form method="post">
      <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Տրանսֆեր</h4></div>
      <div class="modal-body form-horizontal">
        <div class="form-group"><label class="control-label col-sm-4">Ելքային դրամարկղ:</label><div class="col-sm-8"><select class="form-control exitCashbox">${cashboxOptions}</select></div></div>
        <div class="form-group"><label class="control-label col-sm-4">Մուտքային դրամարկղ:</label><div class="col-sm-8"><select class="form-control enterCashbox">${cashboxOptions}</select></div></div>
        <div class="form-group"><label class="control-label col-sm-4">Գումարի չափը:</label><div class="col-sm-8"><input type="number" name="cashValue" class="form-control cashValue"></div></div>
        <div class="form-group"><label class="control-label col-sm-4">Մեկնաբանություն:</label><div class="col-sm-8"><textarea class="form-control" name="comment"></textarea></div></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button type="submit" class="btn btn-success">Հաստատել</button></div>
    </form></div></div>
  </div>
  <div id="cashEditModal" class="modal fade editModal" role="dialog" data-backdrop="static">
    <div class="modal-dialog"><div class="modal-content"><form id="cacheBoxHistoryForm">
      <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Փոփոխել</h4></div>
      <div class="modal-body form-horizontal">
        <div class="form-group"><label class="control-label col-sm-3">Գումար:</label><div class="col-sm-9"><input type="text" class="form-control m-bot15" name="CacheBoxHistoryForm[cash_add_value]"></div></div>
        <div class="form-group"><label class="control-label col-sm-3">Նպատակ:</label><div class="col-sm-9"><textarea class="form-control m-bot15" name="CacheBoxHistoryForm[comment]"></textarea></div></div>
        <div class="form-group"><label class="control-label col-sm-3">Ում կողմից:</label><div class="col-sm-9"><textarea class="form-control m-bot15" name="CacheBoxHistoryForm[cash_add_autor]"></textarea></div></div>
        <div class="form-group"><label class="control-label col-sm-3">Ծախսի տեսակ:</label><div class="col-sm-9"><select class="form-control m-bot15 expence_type">${typeOptions}</select></div></div>
        <div class="form-group"><label class="control-label col-sm-3">Դրամարկղ:</label><div class="col-sm-9"><select class="form-control m-bot15 cashbox">${cashboxOptions}</select></div></div>
        <div class="form-group"><label class="control-label col-sm-3">Ենթատեսակ:</label><div class="col-sm-9"><select class="form-control m-bot15 sub_expense"><option></option><option>Խոհանոց</option><option>Բար</option></select></div></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-success submit" type="submit">Հաստատել</button></div>
    </form></div></div>
  </div>`;
}

function cashTotalizedContent() {
  const cashboxes = ['Ընդհանուր', 'Տերմինալ', 'Կանխիկ', 'Բանկային', 'IDRAM', 'Մատակարար', 'InecoPay', 'Telcell'];
  const rows = [
    { date: '2026-06-15', enter: 636250, exit: 8000, balance: 8935624, sales: 635250 },
    { date: '2026-06-16', enter: 333950, exit: 904873, balance: 8364701, sales: 294500 },
    { date: '2026-06-17', enter: 0, exit: 0, balance: 8364701, sales: 0 },
    { date: '2026-06-18', enter: 0, exit: 0, balance: 8364701, sales: 0 },
    { date: '2026-06-19', enter: 0, exit: 0, balance: 8364701, sales: 500 },
    { date: '2026-06-20', enter: 0, exit: 0, balance: 8364701, sales: 0 },
    { date: '2026-06-21', enter: 0, exit: 0, balance: 8364701, sales: 0 },
    { date: '2026-06-22', enter: 0, exit: 0, balance: 8364701, sales: 0 },
    { date: '2026-06-23', enter: 500, exit: 0, balance: 8365201, sales: 0 },
    { date: '2026-06-24', enter: 0, exit: 0, balance: 8365201, sales: 0 },
    { date: '2026-06-25', enter: 0, exit: 0, balance: 8365201, sales: 0 },
    { date: '2026-06-26', enter: 0, exit: 0, balance: 8365201, sales: 0 },
    { date: '2026-06-27', enter: 0, exit: 0, balance: 8365201, sales: 0 },
    { date: '2026-06-28', enter: 0, exit: 0, balance: 8365201, sales: 0 }
  ];

  const tabs = cashboxes.map((item, index) => `<li class="${index === 0 ? 'active' : ''}">
    <a href="${index === 0 ? '?cash=all' : `?cash=${index}`}">${item}</a>
  </li>`).join('');

  const bodyRows = rows.map(row => `<tr>
    <td>${row.date}</td>
    <td>${row.enter}</td>
    <td>${row.exit}</td>
    <td>${row.balance}</td>
    <td>${row.sales}</td>
  </tr>`).join('');

  const totalEnter = rows.reduce((sum, row) => sum + row.enter, 0);
  const totalExit = rows.reduce((sum, row) => sum + row.exit, 0);
  const totalSales = rows.reduce((sum, row) => sum + row.sales, 0);
  const lastBalance = rows[rows.length - 1].balance;

  return `<div class="cash-page cash-totalized-page">
    <section class="panel">
      <header class="panel-heading">
        <ul class="nav nav-tabs menuButtonsContainer">${tabs}</ul>
      </header>
      <div class="panel-body">
        <form method="get" class="cash-filter-form cash-totalized-filter">
          <div class="row">
            <div class="input-group input-large col-md-6 col-sm-12 col-xs-12 m-bot15 header_filter cash-date-filter" data-date-format="yyyy/mm/dd" id="totalizedDateRange">
              <input type="text" class="form-control dpd1" value="2026-07-03" name="datepicker_start_date">
              <span class="input-group-addon inputsDivider">ից</span>
              <input type="text" class="form-control dpd2" value="2026-07-03" name="datepicker_end_date">
            </div>

            <div class="input-group input-large padding-left0 col-md-5 col-sm-10 col-xs-12 header_filter cash-time-filter">
              <div class="input-group bootstrap-timepicker">
                <span class="input-group-btn">
                  <button class="btn btn-default button-time-icon brn timeIcon1" type="button"><i class="icon-time"></i></button>
                </span>
                <input type="text" class="form-control timepicker-24 tp1 border-radius0" name="clock_start" value="00:00:00">
              </div>
              <span class="input-group-addon border-radius0 inputsDivider">ից</span>
              <div class="input-group bootstrap-timepicker">
                <input type="text" class="form-control timepicker-24 tp2 border-radius0" name="clock_end" value="23:59:59">
                <span class="input-group-btn">
                  <button class="btn btn-default button-time-icon bln timeIcon2" type="button"><i class="icon-time"></i></button>
                </span>
              </div>
            </div>

            <div class="col-md-1 col-sm-2 col-xs-12 cash-filter-button-col">
              <button class="btn btn-md btn-danger padding5 cash-filter-button" type="submit" name="datetimepickerSubmit">Ֆիլտրել</button>
            </div>
          </div>
        </form>

        <div class="row cash-totalized-tools">
          <div class="col-sm-6">
            <button class="btn btn-warning m-r-10">Տպել <i class="fa fa-print"></i></button>
            <button class="btn btn-primary">Excel <i class="fa fa-table"></i></button>
          </div>
          <div class="col-sm-6 text-right cash-totalized-search">
            <label>Փնտրել</label>
            <input type="text" class="form-control" placeholder="">
          </div>
        </div>

        <div class="grid-view">
          <table class="table table-bordered cashboxtable table-responsive">
            <thead>
              <tr>
                <th>Ամսաթիվ <span class="sort-arrows">↕</span></th>
                <th>Մուտք <span class="sort-arrows">↕</span></th>
                <th>Ելք <span class="sort-arrows">↕</span></th>
                <th>Մնացորդ <span class="sort-arrows">↕</span></th>
                <th>Մուտք վաճառքից <span class="sort-arrows">↕</span></th>
              </tr>
              <tr class="column-search">
                <td><input type="text" class="form-control" placeholder="Փնտրել"></td>
                <td><input type="text" class="form-control" placeholder="Փնտրել"></td>
                <td><input type="text" class="form-control" placeholder="Փնտրել"></td>
                <td><input type="text" class="form-control" placeholder="Փնտրել"></td>
                <td><input type="text" class="form-control" placeholder="Փնտրել"></td>
              </tr>
            </thead>
            <tbody>${bodyRows}</tbody>
            <tfoot>
              <tr>
                <th>Ընդհանուր</th>
                <th>${totalEnter}</th>
                <th>${totalExit}</th>
                <th>${lastBalance}</th>
                <th>${totalSales}</th>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </section>
  </div>`;
}

function cashSettingsContent() {
  const cashboxes = [
    { name: 'Տերմին', nameRu: '', nameEn: '', def: 'Ոչ', bank: 'Ոչ', fiscal: 'Ըստ Օգտատիրոջ', card: 'Ոչ', deleted: false },
    { name: 'Կանխիկ', nameRu: '', nameEn: '', def: 'Այո', bank: 'Ոչ', fiscal: 'Ըստ Օգտատիրոջ', card: 'Ոչ', deleted: false },
    { name: 'Բանկային', nameRu: '', nameEn: '', def: 'Ոչ', bank: 'Այո', fiscal: 'Ըստ Օգտատիրոջ', card: 'Ոչ', deleted: false },
    { name: 'IDRAM', nameRu: '', nameEn: '', def: 'Ոչ', bank: 'Այո', fiscal: 'Ըստ Օգտատիրոջ', card: 'Ոչ', deleted: false },
    { name: 'Մատակարար', nameRu: '', nameEn: '', def: 'Ոչ', bank: 'Ոչ', fiscal: 'Ըստ Օգտատիրոջ', card: 'Ոչ', deleted: false },
    { name: 'InecoPay', nameRu: '', nameEn: '', def: 'Ոչ', bank: 'Այո', fiscal: 'Ըստ Օգտատիրոջ', card: 'Ոչ', deleted: false },
    { name: 'Raykom Կանխիկ', nameRu: '', nameEn: '', def: 'Ոչ', bank: 'Ոչ', fiscal: 'Ըստ Օգտատիրոջ', card: 'Ոչ', deleted: true },
    { name: 'Raykom Բանկային', nameRu: '', nameEn: '', def: 'Ոչ', bank: 'Այո', fiscal: 'Ըստ Օգտատիրոջ', card: 'Ոչ', deleted: true },
    { name: 'Telcell', nameRu: 'Telcell', nameEn: 'Telcell', def: 'Ոչ', bank: 'Այո', fiscal: 'Ըստ Օգտատիրոջ', card: 'Ոչ', deleted: false },
    { name: 'Menu.am', nameRu: 'Menu.am', nameEn: 'Menu.am', def: 'Ոչ', bank: 'Այո', fiscal: 'Ըստ Օգտատիրոջ', card: 'Ոչ', deleted: true }
  ];

  const rows = cashboxes.map((row, index) => `<tr>
    <td>${row.name}</td>
    <td>${row.nameRu}</td>
    <td>${row.nameEn}</td>
    <td>${row.def}</td>
    <td>${row.bank}</td>
    <td>${row.fiscal}</td>
    <td>${row.card}</td>
    <td>
      <div class="inTableButtonsContainer">
        <button class="btn btn-xs edit-btn btn-warning inTableIconButton" data-id="${index + 1}" data-toggle="modal" data-target="#cashboxEditModal">
          <img src="assets/img/icons/pencil.svg" alt="">
        </button>
        ${row.deleted ? `<button class="btn btn-xs active-btn btn-success cashbox-activate-btn" data-id="${index + 1}">
          <i class="icon-ok"></i> Ակտիվացնել
        </button>` : `<button class="btn btn-xs del-btn btn-danger inTableIconButton" data-id="${index + 1}">
          <img src="assets/img/icons/trash.svg" alt="">
        </button>`}
      </div>
    </td>
  </tr>`).join('');

  const filterCells = Array.from({ length: 8 }).map(() => '<td><input type="text" class="form-control" placeholder="Փնտրել"></td>').join('');

  return `<div class="cash-page cash-settings-page">
    <div class="row">
      <div class="col-xs-12">
        <section class="panel">
          <div class="panel-body">
            <button class="btn btn-success m-bot15 cashbox-add-button" data-toggle="modal" data-target="#cashboxAddModal">
              <img src="assets/img/icons/plusIcon.svg" alt="">
              Ավելացնել
            </button>
            <div class="cashbox-settings-grid dataTables_wrapper form-inline">
              <div class="row cashbox-settings-grid-top">
                <div class="col-sm-6"></div>
                <div class="col-sm-6 text-right cashbox-settings-search">
                  <label>Փնտրել:
                    <input type="text" class="form-control input-sm">
                  </label>
                </div>
              </div>
              <div class="table-responsive cashbox-settings-table-wrap">
                <table class="table table-bordered mytable cashbox-settings-table">
                  <thead>
                    <tr>
                      <th>Անուն <span class="sort-arrows">↕</span></th>
                      <th>Անուն(ru) <span class="sort-arrows">↕</span></th>
                      <th>Անուն(en) <span class="sort-arrows">↕</span></th>
                      <th>Լռելյայն <span class="sort-arrows">↕</span></th>
                      <th>Անկանխիկ <span class="sort-arrows">↕</span></th>
                      <th>Տպել ՀԴՄ անդորագիր <span class="sort-arrows">↕</span></th>
                      <th>Քարտ <span class="sort-arrows">↕</span></th>
                      <th><i class="icon-cogs"></i> <span class="sort-arrows">↕</span></th>
                    </tr>
                    <tr class="filters">${filterCells}</tr>
                  </thead>
                  <tbody>${rows}</tbody>
                </table>
              </div>
              <div class="row cashbox-settings-grid-bottom">
                <div class="col-sm-5">
                  <div class="dataTables_info">Ցուցադրված է 1-ից 10-ը 10 գրառումից</div>
                </div>
                <div class="col-sm-7">
                  <div class="dataTables_paginate paging_bootstrap pagination">
                    <ul><li class="prev disabled"><a href="#">Նախորդ</a></li><li class="active"><a href="#">1</a></li><li class="next disabled"><a href="#">Հաջորդ</a></li></ul>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>

    <div id="cashboxAddModal" class="modal fade form-horizontal cashbox-modal" role="dialog">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <h4 class="modal-title">Ավելացնել Դրամարկղ</h4>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label class="control-label col-sm-3">name:</label>
              <div class="col-sm-9"><input type="text" required class="form-control cashbox_name" name="cashbox_name"></div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-3">name:(en)</label>
              <div class="col-sm-9"><input type="text" class="form-control cashbox_name_en" name="cashbox_name_en"></div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-3">name:(ru)</label>
              <div class="col-sm-9"><input type="text" class="form-control cashbox_name_ru" name="cashbox_name_ru"></div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-3">Լռելյայն:</label>
              <div class="col-sm-9"><select class="form-control set_default" name="set_default"><option value="no">no</option><option value="yes">yes</option></select></div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-3">Անկանխիկ:</label>
              <div class="col-sm-9"><select class="form-control is-bank" name="is-bank"><option value="0">no</option><option value="1">yes</option></select></div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-3">Քարտային:</label>
              <div class="col-sm-9"><select class="form-control is-card" name="is-card"><option value="0">no</option><option value="1">yes</option></select></div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-3">Տպել Հդմ:</label>
              <div class="col-sm-9"><select class="form-control print_fiscal" name="print_fiscal"><option value="10">Ընստ օգտատիրոջ</option><option value="20">Միշտ տպել</option><option value="30">Երբեք չտպել</option></select></div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button>
              <button class="btn btn-success add-cachebox-btn pull-right">Հաստատել</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div id="cashboxEditModal" class="modal fade form-horizontal cashbox-modal" role="dialog">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <h4 class="modal-title">Փոփոխել Դրամարկղը</h4>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label class="control-label col-sm-3">name:</label>
              <div class="col-sm-9"><input type="text" required class="form-control" id="cashboxSettingsName" name="cashbox_name" value="Կանխիկ"></div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-3">name:(en)</label>
              <div class="col-sm-9"><input type="text" class="form-control" id="cashboxSettingsNameEn" name="cashbox_name_en" value="Cash"></div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-3">name:(ru)</label>
              <div class="col-sm-9"><input type="text" class="form-control" id="cashboxSettingsNameRu" name="cashbox_name_ru" value="Наличные"></div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-3">Լռելյայն:</label>
              <div class="col-sm-9"><select class="form-control" id="cashboxSettingsDefault" name="set_default"><option value="no">no</option><option value="yes" selected>yes</option></select></div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-3">Անկանխիկ:</label>
              <div class="col-sm-9"><select class="form-control" id="cashboxSettingsBank" name="is_bank"><option value="0" selected>no</option><option value="1">yes</option></select></div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-3">Քարտային:</label>
              <div class="col-sm-9"><select class="form-control" id="cashboxSettingsCard" name="is_card"><option value="0" selected>no</option><option value="1">yes</option></select></div>
            </div>
            <div class="form-group">
              <label class="control-label col-sm-3">Տպել Հդմ:</label>
              <div class="col-sm-9"><select class="form-control" id="cashboxSettingsFiscal" name="print_fiscal"><option value="10" selected>Ընստ օգտատիրոջ</option><option value="20">Միշտ տպել</option><option value="30">Երբեք չտպել</option></select></div>
            </div>
            <input type="hidden" id="cashboxSettingsRowId" value="1">
            <div class="modal-footer">
              <button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button>
              <button class="btn btn-success pull-right edit-cachebox-btn">Հաստատել</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>`;
}

function expenseTypesContent() {
  const expenseTypes = [
    { name: 'Այլ', ru: 'Другое', en: 'Other', subs: ['Խոհանոց', 'Բար'] },
    { name: 'Վաճառք', ru: 'Продажа', en: 'Sale', subs: ['Սեղաններից', 'Արագ սնունդ'] },
    { name: 'Ծախս', ru: 'Расход', en: 'Expense', subs: ['Կոմունալ', 'Տնտեսական'] },
    { name: 'Գնում', ru: 'Покупка', en: 'Purchase', subs: ['Մթերք', 'Խմիչք'] },
    { name: 'Աշխատավարձ', ru: 'Зарплата', en: 'Salary', subs: ['Մատուցող', 'Խոհարար'] },
    { name: 'Պարտքի մարում', ru: 'Погашение долга', en: 'Debt payment', subs: ['Մատակարար', 'Հաճախորդ'] }
  ];

  const relatedTypes = [
    'Վաճառք սեղաններից',
    'Վաճառք համարներից',
    'Վաճառք արագ սնունդից',
    'Գնման փաստաթուղթ',
    'Ընկերության պարտքի մարում',
    'Հաճախորդի պարտքի մարում',
    'Աշխատավարձի վճարում'
  ];

  const optionList = ['Այլ', ...expenseTypes.slice(1).map(type => type.name)]
    .map((name, index) => `<option value="${index}">${name}</option>`)
    .join('');

  const rows = expenseTypes.map((type, index) => `<tr>
    <td>${type.name}</td>
    <td>
      <div class="inTableButtonsContainer">
        <button type="button" class="btn btn-danger btn-xs btn_delete inTableIconButton" data-toggle="modal" data-target="#expenseDeleteModal" value="${index + 1}">
          <img src="assets/img/icons/trash.svg" alt="">
        </button>
        <button type="button" class="btn btn-warning btn-xs btn_edit inTableIconButton" data-table="expence_types" data-toggle="modal" data-target="#expenseEditModal" value="${index + 1}">
          <img src="assets/img/icons/pencil.svg" alt="">
        </button>
        <button type="button" class="btn btn-info btn-xs sub_expense inTableIconButton" data-id="${index + 1}" data-toggle="modal" data-target="#expenseSubModal">
          <i class="icon-list"></i>
        </button>
      </div>
    </td>
  </tr>`).join('');

  const relateRows = relatedTypes.map((label, index) => `<div class="form-group">
    <label>${label}</label>
    <select name="related_${index + 1}" class="form-control">
      ${optionList}
    </select>
  </div>`).join('');

  const subRows = expenseTypes[0].subs.map(name => `<tr>
    <td>${name}</td>
    <td><button class="btn btn-danger btn-xs inTableIconButton"><img src="assets/img/icons/trash.svg" alt=""></button></td>
  </tr>`).join('');

  return `<div class="expense-types-page">
    <div class="row">
      <div class="col-xs-12">
        <section class="panel">
          <div class="panel-body">
            <div class="expense-types-actions">
              <button class="btn btn-success m-bot15" data-toggle="modal" data-target="#expenseAddModal">
                <img src="assets/img/icons/plusIcon.svg" alt="">
                Ավելացնել
              </button>
              <button class="btn btn-info get_expense_type_setting m-bot15" data-toggle="modal" data-target="#expenseRelateModal">
                <i class="icon-cogs"></i> Կարգավորումներ
              </button>
            </div>

            <div class="expense-types-grid dataTables_wrapper form-inline">
              <div class="row expense-types-grid-top">
                <div class="col-sm-6"></div>
                <div class="col-sm-6 text-right expense-types-search">
                  <label>Փնտրել:
                    <input type="text" class="form-control input-sm">
                  </label>
                </div>
              </div>
              <table class="table table-bordered mytable expense-types-table">
                <thead>
                  <tr>
                    <th>Անվանում <span class="sort-arrows">↕</span></th>
                    <th><i class="icon-cogs"></i> <span class="sort-arrows">↕</span></th>
                  </tr>
                  <tr class="filters">
                    <td><input type="text" class="form-control" placeholder="Փնտրել"></td>
                    <td><input type="text" class="form-control" placeholder="Փնտրել"></td>
                  </tr>
                </thead>
                <tbody>${rows}</tbody>
              </table>
              <div class="row expense-types-grid-bottom">
                <div class="col-sm-5"><div class="dataTables_info">Ցուցադրված է 1-ից 6-ը 6 գրառումից</div></div>
                <div class="col-sm-7"><div class="dataTables_paginate paging_bootstrap pagination"><ul><li class="prev disabled"><a href="#">Նախորդ</a></li><li class="active"><a href="#">1</a></li><li class="next disabled"><a href="#">Հաջորդ</a></li></ul></div></div>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>

    <div id="expenseAddModal" class="modal fade form-horizontal expense-type-modal" role="dialog">
      <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Ավելացնել</h4></div>
        <div class="modal-body">
          <div class="form-group"><label class="control-label col-sm-3">Ծախսի տեսակ:</label><div class="col-sm-9"><input type="text" required class="form-control data" name="name"></div></div>
          <div class="form-group"><label class="control-label col-sm-3">Ծախսի խումբ: Ռուսերեն:</label><div class="col-sm-9"><input type="text" required class="form-control data" name="title_ru"></div></div>
          <div class="form-group"><label class="control-label col-sm-3">Ծախսի խումբ։ Անգլերեն:</label><div class="col-sm-9"><input type="text" required class="form-control data" name="title_en"></div></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-success pull-right addSubmit" type="button" data-table="expence_types">Հաստատել</button></div>
      </div></div>
    </div>

    <div id="expenseDeleteModal" class="modal fade expense-type-modal" role="dialog">
      <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h4 class="modal-title">Ջնջել ծախսի տեսակը</h4></div>
        <div class="modal-body"><p>Ջնջե՞լ նշված ծախսի տեսակը</p></div>
        <div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-danger pull-right delete_submit" type="button" data-table="expence_types">Հաստատել</button></div>
      </div></div>
    </div>

    <div id="expenseEditModal" class="modal fade form-horizontal expense-type-modal" role="dialog">
      <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Փոփոխել</h4></div>
        <div class="modal-body">
          <div class="form-group"><label class="control-label col-sm-3">Ծախսի տեսակ:</label><div class="col-sm-9"><input type="text" required class="form-control data" name="name" value="Ծախս"></div></div>
          <div class="form-group"><label class="control-label col-sm-3">Ծախսի խումբ: Ռուսերեն:</label><div class="col-sm-9"><input type="text" required class="form-control data" name="title_ru" value="Расход"></div></div>
          <div class="form-group"><label class="control-label col-sm-3">Ծախսի խումբ։ Անգլերեն:</label><div class="col-sm-9"><input type="text" required class="form-control data" name="title_en" value="Expense"></div></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-warning pull-right editSubmit" type="button" data-table="expence_types">Հաստատել</button></div>
      </div></div>
    </div>

    <div id="expenseRelateModal" class="modal fade expense-type-modal" role="dialog">
      <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h4 class="modal-title">Ընտրել ծախսի տեսակը</h4></div>
        <div class="modal-body">${relateRows}</div>
        <div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-success pull-right expense_type_setting_submit" type="submit" name="relate">Հաստատել</button></div>
      </div></div>
    </div>

    <div id="expenseSubModal" class="modal fade expense-sub-modal" role="dialog">
      <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Ենթածախս</h4></div>
        <div class="modal-body clearfix">
          <div class="expense-sub-add">
            <div class="form-group clearfix">
              <div class="col-sm-12 sub_">
                <div><label class="control-label">Անվանում։ Հայերեն</label><input type="text" required id="expenseSubNameHy" name="expenseName" class="form-control"></div>
                <div><label class="control-label">Անվանում: Ռուսերեն</label><input type="text" required id="expenseSubNameRu" name="expenseNameRu" class="form-control"></div>
                <div><label class="control-label">Անվանում։ Անգլերեն</label><input type="text" required id="expenseSubNameEn" name="expenseNameEn" class="form-control"></div>
                <input type="hidden" id="expenseSubId">
              </div>
            </div>
            <div class="form-group clearfix"><button class="btn btn-success" id="expenseAddSub">Ավելացնել</button></div>
          </div>
          <div class="row modal_Table">
            <table class="table table-bordered table-inbox subExpenseTable">
              <thead><tr><th>Անվանում</th><th><i class="icon-trash"></i></th></tr></thead>
              <tbody>${subRows}</tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button></div>
      </div></div>
    </div>
  </div>`;
}

function settingsContent(title = 'Կարգավորումներ') {
  const checkbox = (id, text, checked = true) => `<label for="${id}" class="settings-option"><input name="${id}" id="${id}" type="checkbox"${checked ? ' checked' : ''}> <span>${text}</span></label>`;
  const radio = (id, value, text, checked = false) => `<label for="${id}" class="settings-option"><input name="sample-radio2" id="${id}" value="${value}" type="radio"${checked ? ' checked' : ''}> <span>${text}</span></label>`;
  const info = text => `<p class="settings-info"><i class="icon-info-sign"></i> ${text}</p>`;
  const switcher = (id, checked = true) => `<label class="settings-switch" for="${id}"><input id="${id}" type="checkbox"${checked ? ' checked' : ''}><span class="settings-switch-ui" data-on="Այո" data-off="Ոչ"></span></label>`;

  return `<div class="settings-page">
    <div class="row">
      <div class="col-lg-12 set">
        <section class="panel settings-panel">
          <div class="panel-body settings-body">
            <div class="settings-head">
              <h3>${title}</h3>
              <span class="settings-head-note">Ընդհանուր կարգավորումներ</span>
            </div>

            <div class="settings-section">
              <label class="settings-title">Սեղաններ / Սրահներ բաժնում, սեղանին կցվող անձնակազմի ցանկում երևան հետևյալ հաստիքները՝</label>
              <div class="settings-options control-parent" id="waiter_list">
                ${checkbox('matucox', 'Մատուցող')}
                ${checkbox('matucoxi_ognakan', 'Մատուցողի օգնական')}
                ${checkbox('gandzapah', 'Գանձապահ')}
                ${checkbox('barmen', 'Բարմեն')}
              </div>
            </div>

            <div class="settings-section">
              <label class="settings-title">Ադմինիստրատիվ արգելափակումներ</label>
              <div class="settings-options settings-options-grid control-parent" id="admin_log">
                ${checkbox('add_prod_after_one', 'Սեղանի հաշիվը տպելուց հետո նոր պատվեր ավելացնել')}
                ${checkbox('sale', 'Հաշվի զեղչում')}
                ${checkbox('to_sale_product', 'Ապրանքային զեղչ')}
                ${checkbox('transform_table', 'Տեղափոխել սեղանը')}
                ${checkbox('print_after_one', 'Սեղանի հաշիվը նորից տպել')}
                ${checkbox('close_no_price', 'Սեղանի զրոյացում ու փակում')}
                ${checkbox('prod_del', 'Սեղանի վրայից պատվերի ջնջում')}
                ${checkbox('partqov', 'Սեղանը պարտքով փակել')}
                ${checkbox('day_report', 'Օրվա հաշվետվության տպում')}
                ${checkbox('end_date', 'Օրվա վերջի կատարում')}
                ${checkbox('cash_box_report', 'Դրամարկղի հաշվետվություն')}
                ${checkbox('cash_edit', 'Դրամարկղի նպատակի փոփոխություն')}
                ${checkbox('close_table', 'Սեղանի հաստատում և փակում')}
                ${checkbox('fast_food_admin_login', 'Արագ սնունդի ադմինիստրատիվ մուտք')}
                ${checkbox('set_staff', 'Նշել վաճառող')}
                ${checkbox('advance_payment', 'Կանխավճար')}
              </div>
              ${info('Նշված գործողություններից ընտրեք թե որոնք պետք է լինեն ադմինիստրատորի գաղտնաբառով արգելափակված և որոնք հասանելի լինեն մատուցողին կամ գանձապահին։')}
            </div>

            <div class="settings-section">
              <label class="settings-title">Հաշվի կլորացում</label>
              <div class="settings-options settings-radios" data-type="hashvi_kloracum">
                ${radio('radio-01', '0', 'Կլորացում մինչև ամբողջական թվի (123.456 => 123)')}
                ${radio('radio-02', '-1', 'Կլորացում մինչև տասնորդական թվի (123.456 => 120)', true)}
                ${radio('radio-05', '50', 'Կլորացում մինչև հիսուներորդական թվի (123.456 => 100, 125.456 => 150)')}
                ${radio('radio-03', '-2', 'Կլորացում մինչև հարյուրերորդական թվի (123.456 => 100, 153.456 => 200)')}
                ${radio('radio-04', '2', 'Չկլորացնել հաշիվը')}
              </div>
            </div>

            <div class="settings-section">
              <label class="settings-title">Տոկոսային սպասարկման վճարի ազդեցությունը</label>
              <div class="settings-options control-parent" id="percent_impact">
                ${checkbox('fix', 'Հաստատագրված վճարի վրա')}
                ${checkbox('time', 'Ժամավճարի վրա')}
              </div>
              ${info('Եթե սեղանի սպասարկման վճարի տիպը ընտրված է «Հաստատագրված + Տոկոսային» կամ «Ժամավճար + Տոկոսային», այս կարգավորումները որոշում են՝ տոկոսը կիրառվի նաև հաստատագրված գումարի կամ ժամավճարի վրա։')}
            </div>

            <div class="settings-section">
              <div class="settings-switch-row">
                <label class="settings-title">Մատուցողի կցվող համակարգ</label>
                ${switcher('requary_staff_present')}
              </div>
              ${info('Ակտիվացնելու դեպքում ամեն սեղան բացելուց պետք է մատուցող կցվի տվյալ սեղանին, և հաշվետվություններում կերևա տվյալ մատուցողի կատարած առևտուրը։')}
              <div class="settings-options control-parent" id="staff_functions">
                ${checkbox('matucoxi_hskoxutyun', 'Մատուցողների գաղտնիացում')}
                ${checkbox('pin_waiter_delivery', 'Կցել մատուցող առաքման սեղանին')}
              </div>
            </div>

            <div class="settings-section settings-section-last">
              <div class="settings-switch-row">
                <label class="settings-title">Prohibit selling out of balance</label>
                ${switcher('sell_without_remnant', false)}
              </div>
              <div class="settings-select-row">
                <label class="settings-title">Արագ սննդի դասավորության տեսակ</label>
                <select class="form-control setting_maker" data-type="fast_food_menu_sorting">
                  <option value="\`sort_num\`">Ըստ օգտատիրոջ դասավորության</option>
                  <option value="\`name\`" selected>Ըստ անվան</option>
                  <option value="\`price\`">Ըստ արժեքի</option>
                </select>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>
  </div>`;
}

function settingsUsersContent() {
  const positions = ['Ադմինիստրատոր', 'Գանձապահ', 'Մատուցող', 'Խոհարար'];
  const users = [
    ['admin', 'Այո', 'Հայերեն'],
    ['cashier01', 'Ոչ', 'Հայերեն'],
    ['waiter01', 'Ոչ', 'Ընտրել մուտք լինելիս'],
    ['kitchen', 'Ոչ', 'Ռուսերեն'],
  ];
  const permissionFields = [
    'Տեղեկատու',
    'Արագ սնունդ',
    'Հաճախորդի պատվեր',
    'Սրահներ / Սեղաններ',
    'Դրամարկղ',
    'Հաշվետվություններ',
    'Պահեստ',
    'Մենյու',
    'Ընկերություններ',
    'Հաճախորդներ',
    'Անձնակազմ',
    'Կարգավորումներ',
  ];
  const select = (items, selected = '') => `<select class="form-control">${items.map(item => `<option${item === selected ? ' selected' : ''}>${item}</option>`).join('')}</select>`;
  const formRow = (label, control) => `<div class="row"><div class="col-xs-8">${label}</div><div class="col-xs-4">${control}</div></div>`;
  const permissionRows = permissionFields.map((label, index) => `<div class="row settings-users-permission-row"><div class="pull-left">${label}</div><div class="pull-right"><label class="radio-inline"><input type="radio" name="perm_${index}" value="1"${index < 5 ? ' checked' : ''}> Ակտիվացնել</label><label class="radio-inline"><input type="radio" name="perm_${index}" value="0"${index >= 5 ? ' checked' : ''}> Անջատել</label></div></div>`).join('');
  const accessRows = [
    ['Հասանելի Սրահներ', select(['Main Hall', 'VIP սրահ', 'Terrace'])],
    ['Հասանելի Դրամարկղեր', select(['Ընդհանուր', 'Կանխիկ', 'Բանկային'])],
    ['Հասանելի Մենյուներ', select(['Հիմնական մենյու', 'Բար', 'Առաքում'])],
    ['Հասանելի Պահեստներ', select(['Հիմնական պահեստ', 'Բար պահեստ', 'Խոհանոց'])],
  ].map(([label, control]) => formRow(label, control)).join('');
  const positionForm = (mode = 'add') => `
    ${formRow('Անվանում (hy)', `<input type="text" class="form-control" value="${mode === 'edit' ? 'Մատուցող' : ''}">`)}
    ${formRow('Անվանում (ru)', `<input type="text" class="form-control" value="${mode === 'edit' ? 'Официант' : ''}">`)}
    ${formRow('Անվանում (en)', `<input type="text" class="form-control" value="${mode === 'edit' ? 'Waiter' : ''}">`)}
    ${formRow('Default language', select(['Հայերեն', 'Անգլերեն', 'Ռուսերեն'], 'Հայերեն'))}
    ${formRow('Special', select(['Ոչ', 'Այո'], mode === 'edit' ? 'Ոչ' : 'Ոչ'))}
    ${permissionRows}
    ${accessRows}`;
  const userForm = (mode = 'add') => `
    ${formRow('Ընտրել հաստիք', select(['Ընտրել', ...positions], mode === 'edit' ? 'Մատուցող' : 'Ընտրել'))}
    ${formRow('Անուն (Ամենաքիչը 4 տառ)', `<input type="text" class="form-control" value="${mode === 'edit' ? 'waiter01' : ''}">`)}
    ${formRow('Գաղտնաբառ (Ամենաքիչը 4 տառ)', '<input type="password" class="form-control">')}
    ${formRow('Printer Name', `<input type="text" class="form-control" value="${mode === 'edit' ? 'BAR_PRINTER' : ''}">`)}
    ${formRow('Ամսաթվի արգելք', '<input type="number" class="form-control" placeholder="Օրերի քանակ">')}
    ${formRow('Default language', select(['Ընտրել մուտք լինելիս', 'Հայերեն', 'Անգլերեն', 'Ռուսերեն'], mode === 'edit' ? 'Հայերեն' : 'Ընտրել մուտք լինելիս'))}
    ${formRow('Special', select(['Այո', 'Ոչ'], mode === 'edit' ? 'Ոչ' : 'Այո'))}
    ${permissionRows}
    ${accessRows}
    ${formRow('Կցված մատուցող', select(['-', 'Արամ Սարգսյան', 'Նարե Մկրտչյան']))}
    ${formRow('Կցել Device', select(['-', 'POS-01', 'Tablet-02']))}
    ${formRow('pos_terminal_ip', '<input type="text" class="form-control" value="192.168.1.25">')}
    ${formRow('pos_ip', '<input type="text" class="form-control" value="192.168.1.30">')}`;
  const modal = (id, title, body, submitClass = 'btn-success') => `<div id="${id}" class="modal fade settings-users-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">${title}</h4></div><div class="modal-body">${body}</div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn ${submitClass} pull-right" type="button">Հաստատել</button></div></div></div></div>`;
  const deleteModal = (id, text) => `<div id="${id}" class="modal fade settings-users-modal settings-users-delete-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-body"><p>${text}</p></div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-danger pull-right" type="button"><img src="assets/img/icons/trash.svg" alt=""> Հաստատել</button></div></div></div></div>`;
  const roleRows = positions.map((name, index) => `<tr data-id="${index + 1}"><td>${name}</td><td><div class="inTableButtonsContainer"><button class="btn btn-xs btn-danger inTableIconButton" data-toggle="modal" data-target="#deleteRoleModal"><img src="assets/img/icons/trash.svg" alt=""></button><button class="btn btn-xs btn-warning inTableIconButton" data-toggle="modal" data-target="#editPositionsModal"><img src="assets/img/icons/pencil.svg" alt=""></button></div></td></tr>`).join('');
  const userRows = users.map((row, index) => `<tr data-id="${index + 1}">${row.map(cell => `<td>${cell}</td>`).join('')}<td class="cog_btns_td"><div class="flexBtns"><a href="#editUserModal" data-toggle="modal" class="btn btn-warning btn-xs inTableIconButton"><img src="assets/img/icons/pencil.svg" alt=""></a><a href="#copyUserModal" data-toggle="modal" class="btn btn-default btn-xs inTableIconButton"><i class="fa fa-copy"></i></a><a href="#deleteUserModal" data-toggle="modal" class="btn btn-danger btn-xs inTableIconButton"><img src="assets/img/icons/trash.svg" alt=""></a></div></td></tr>`).join('');

  return `<div class="settings-users-page">
    <section class="panel settings-users-panel">
      <div class="panel-body">
        <button class="btn btn-success settings-users-add-btn" data-target="#addPositionsModal" data-toggle="modal"><img src="assets/img/icons/plusIcon.svg" alt=""> Ավելացնել հաստիք</button>
        <div class="settings-users-role-table-wrap">
          <table class="table table-hover" id="role-table">
            <thead><tr><th>Անվանում</th><th><i class="icon-cogs"></i></th></tr></thead>
            <tbody>${roleRows}</tbody>
          </table>
        </div>
        <hr class="new2">
        <div class="settings-users-user-top">
          <button class="btn btn-success settings-users-add-btn" data-target="#addUserModal" data-toggle="modal"><img src="assets/img/icons/plusIcon.svg" alt=""> Ավելացնել օգտատեր</button>
          <a class="dt-button buttons-excel buttons-html5 ExcelButton" tabindex="0"><span><button class="btn btn-primary excelButton" type="button"><img src="assets/img/icons/ExcelLogo.svg" alt=""> Excel</button></span></a>
        </div>
        <div class="settings-users-toolbar"><label>Ցույց տալ <select class="form-control input-sm"><option selected>10</option><option>25</option><option>50</option></select> գրառում</label></div>
        <div class="settings-users-table-wrap">
          <table class="table table-striped table-bordered" id="companyGridTable">
            <thead>
              <tr><th>Անուն</th><th>Special</th><th>Default language</th><th class="cogs"><i class="fa fa-cogs" aria-hidden="true"></i></th></tr>
              <tr class="filters"><td><input class="form-control" type="text"></td><td>${select(['', 'Այո', 'Ոչ'])}</td><td>${select(['', 'Հայերեն', 'Ռուսերեն', 'Անգլերեն'])}</td><td></td></tr>
            </thead>
            <tbody>${userRows}</tbody>
          </table>
        </div>
        <div class="settings-users-footer"><div>Ցուցադրված է 1-ից 4-ը 4 տողից</div><div class="settings-users-pagination"><button class="btn btn-default btn-sm disabled">Նախորդը</button><button class="btn btn-danger btn-sm active">1</button><button class="btn btn-default btn-sm disabled">Հաջորդը</button></div></div>
      </div>
    </section>
    ${modal('editPositionsModal', 'Փոփոխել', positionForm('edit'))}
    ${modal('addPositionsModal', 'Ավելացնել', positionForm('add'))}
    ${deleteModal('deleteRoleModal', 'Ջնջե՞լ հաստիքը')}
    ${modal('addUserModal', 'Ավելացնել', userForm('add'))}
    ${modal('editUserModal', 'Փոփոխել', userForm('edit'))}
    ${deleteModal('deleteUserModal', 'Ջնջե՞լ օգտատիրոջը')}
    ${modal('copyUserModal', 'Պատճենել', `${formRow('Անուն (Ամենաքիչը 4 տառ)', '<input type="text" class="form-control">')}${formRow('Գաղտնաբառ (Ամենաքիչը 4 տառ)', '<input type="password" class="form-control">')}`)}
  </div>`;
}

function settingsChecksPlaceContent() {
  const fields = [
    ['place_name', 'Անվանում', 'text'],
    ['place_name_ru', 'Անվանում Ռուսերեն', 'text'],
    ['place_name_en', 'Անվանում Անգլերեն', 'text'],
    ['printer_name', 'Printer Name', 'text'],
    ['col_width', 'Width', 'text'],
    ['menu_view', 'Menu view', 'yesno'],
    ['need_check', 'Need check', 'yesno'],
    ['attached_check_place', 'Կցված պրինտեր', 'printer'],
    ['cassa_id', 'Cassa id', 'text'],
    ['check_image_type_new', 'Կտրոնի նկարի ֆորմատ', 'imageType'],
    ['receipt_per_item', 'Կտրոն ամեն ապրանքի համար', 'yesno'],
  ];
  const rows = [
    ['740', '998', '14', 'Kitchen', 'cassa', '450', 'Այո', 'Այո', '0', 'Հին', 'Ոչ'],
    ['541', '998', '14', 'kuxni', 'cassa', '500', 'Այո', 'Այո', '0', 'Հին', 'Ոչ'],
    ['526', '998', '14', 'client', 'cassa', '450', 'Ոչ', 'Այո', '0', 'Հին', 'Ոչ'],
    ['525', '998', '14', 'report', 'cassa', '450', 'Ոչ', 'Այո', '0', 'Հին', 'Ոչ'],
    ['41', '998', '14', 'Բար', 'cassa', '500', 'Այո', 'Այո', '0', 'Հին', 'Ոչ'],
    ['39', '998', '14', 'Խոհանոց', 'cassa', '500', 'Այո', 'Այո', '0', 'Հին', 'Ոչ'],
  ];
  const select = (items, selected = '') => `<select class="form-control data">${items.map(item => `<option${item === selected ? ' selected' : ''}>${item}</option>`).join('')}</select>`;
  const fieldControl = (type, value = '') => {
    if (type === 'yesno') return select(['Այո', 'Ոչ'], value || 'Այո');
    if (type === 'imageType') return select(['Հին', 'Նոր'], value || 'Նոր');
    if (type === 'printer') return select(['-', 'Խոհանոց', 'Բար', 'Հրուշակեղեն'], value || '-');
    return `<input type="text" class="data form-control" value="${value}">`;
  };
  const formRows = (mode = 'add') => fields.map(([key, label, type], index) => {
    const values = mode === 'edit'
      ? ['Խոհանոց', 'Кухня', 'Kitchen', 'KITCHEN_PRINTER', '42', 'Այո', 'Այո', '-', '1', 'Նոր', 'Ոչ']
      : [];
    return `<div class="row"><div class="col-xs-8">${label}</div><div class="col-xs-4">${fieldControl(type, values[index] || '')}</div></div>`;
  }).join('');
  const copyRows = [
    ['Անվանում', '<input type="text" class="form-control data" name="place_name">'],
    ['Անվանում Ռուսերեն', '<input type="text" class="form-control data" name="place_name_ru">'],
    ['Անվանում Անգլերեն', '<input type="text" class="form-control data" name="place_name_en">'],
    ['Տպիչի անուն', '<input type="text" class="form-control data" name="printer_name">'],
  ].map(([label, control]) => `<div class="form-group row"><label class="control-label col-sm-3">${label}:</label><div class="col-sm-9">${control}</div></div>`).join('');
  const modal = (id, title, body, submitClass = 'btn-success') => `<div id="${id}" class="modal fade settings-checks-place-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">${title}</h4></div><div class="modal-body">${body}</div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn ${submitClass} pull-right" type="button">Հաստատել</button></div></div></div></div>`;
  const tableRows = rows.map((row, index) => `<tr data-id="${index + 1}">
      ${row.map(cell => `<td>${cell}</td>`).join('')}
      <td class="cog_btns_td"><div class="flexBtns"><a href="#editPlaceModal" data-toggle="modal" class="btn btn-warning btn-xs inTableIconButton"><img src="assets/img/icons/pencil.svg" alt=""></a><a href="#deletePlaceModal" data-toggle="modal" class="btn btn-danger btn-xs inTableIconButton"><img src="assets/img/icons/trash.svg" alt=""></a><a href="#copyPlaceModal" data-toggle="modal" class="btn btn-default btn-xs inTableIconButton"><i class="fa fa-copy"></i></a></div></td>
    </tr>`).join('');
  const filters = Array.from({ length: 11 }).map(() => '<td><input type="text" class="form-control"></td>').join('');

  return `<div class="settings-checks-place-page">
    <section class="panel settings-checks-place-panel">
      <div class="panel-body pad_320">
        <div class="settings-checks-place-top">
          <button class="btn btn-success settings-checks-place-add-btn" data-target="#addPlaceModal" data-toggle="modal"><img src="assets/img/icons/plusIcon.svg" alt=""> Ավելացնել</button>
        </div>
        <div class="settings-checks-place-toolbar">
          <label><select class="form-control input-sm"><option selected>10</option><option>25</option><option>50</option></select> Ցուցադրված են 1-ից 6-ը ընդհանուր 6-ից.</label>
          <a class="dt-button buttons-excel buttons-html5 ExcelButton" tabindex="0"><span><button class="btn btn-primary excelButton" type="button"><img src="assets/img/icons/ExcelLogo.svg" alt=""> Excel</button></span></a>
        </div>
        <div class="settings-checks-place-table-wrap">
          <table class="table table-striped table-bordered" id="companyGridTable">
            <thead>
              <tr><th>id</th><th>Պրոֆիլի ID</th><th>Դրամարկղի ID</th><th>Անուն</th><th>Տպիչի անուն</th><th>Սյունակի լայնությունը</th><th>Մենյուի տեսանելիություն</th><th>Need Check</th><th>Կցված պրինտեր</th><th>Կտրոնի նկարի ֆորմատ</th><th>Receipt Per Item</th><th class="cogs"><i class="fa fa-cogs" aria-hidden="true"></i></th></tr>
              <tr class="filters">${filters}<td></td></tr>
            </thead>
            <tbody>${tableRows}</tbody>
          </table>
        </div>
        <div class="settings-checks-place-footer"><div></div><div class="settings-checks-place-pagination"><button class="btn btn-default btn-sm disabled">Նախորդը</button><button class="btn btn-danger btn-sm active">1</button><button class="btn btn-default btn-sm disabled">Հաջորդը</button></div></div>
      </div>
    </section>
    ${modal('addPlaceModal', 'Ավելացնել', formRows('add'))}
    <div id="deletePlaceModal" class="modal fade settings-checks-place-modal settings-checks-place-delete-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-body"><p>Ջնջե՞լ պատրաստման վայրը</p></div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-danger pull-right" type="button"><img src="assets/img/icons/trash.svg" alt=""> Հաստատել</button></div></div></div></div>
    ${modal('editPlaceModal', 'Փոփոխել', formRows('edit'))}
    ${modal('copyPlaceModal', 'Պատճենել', copyRows, 'btn-warning')}
    <div id="itemListModal" class="modal fade settings-checks-place-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Պատրաստման վայրը օգտագործվում է տվյալ ապրանքներում ՝</h4></div><div class="modal-body clearfix"><div class="row"><table class="table table-bordered table-hover itemListTable"><thead><tr><th>Անվանում</th><th>Մենյու</th><th>Բաժին</th></tr></thead><tbody><tr><td>Սուրճ</td><td>Բար</td><td>Խմիչքներ</td></tr><tr><td>Սթեյք</td><td>Հիմնական մենյու</td><td>Ուտեստներ</td></tr></tbody></table></div></div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button></div></div></div></div>
  </div>`;
}

function settingsSmsContent() {
  const rows = [
    ['Ծննդյան շնորհավորանք', 'Հարգելի {client}, շնորհավորում ենք Ձեր ծննդյան օրը։'],
    ['Ամրագրման հաստատում', 'Ձեր ամրագրումը հաստատված է։ Սպասում ենք Ձեզ {date}։'],
    ['Պատվերի պատրաստ է', 'Ձեր պատվերը պատրաստ է։ Շնորհակալություն SMARTREST ընտրելու համար։'],
    ['Քարտի բոնուս', 'Ձեր բոնուսային քարտին ավելացվել է {bonus} միավոր։'],
  ];
  const bodyRows = rows.map((row, index) => `<tr data-id="${index + 1}">
      <td>${row[0]}</td>
      <td>${row[1]}</td>
      <td><div class="client_icons"><button class="btn btn-warning btn-xs edit-btn inTableIconButton" data-toggle="modal" data-target="#editModal"><img src="assets/img/icons/pencil.svg" alt=""></button><button class="btn btn-danger btn-xs delete-btn inTableIconButton" data-toggle="modal" data-target="#deleteModal"><img src="assets/img/icons/trash.svg" alt=""></button></div></td>
    </tr>`).join('');
  const formFields = (edit = false) => `<div class="form-group"><label class="control-label col-sm-4">${edit ? 'URl' : 'Անվանում'}:</label><div class="col-sm-8"><input type="text" required class="form-control" name="title"${edit ? ' value="Ծննդյան շնորհավորանք"' : ''}></div></div><div class="form-group"><label class="control-label col-sm-4">${edit ? 'Comment' : 'Տեքստ'}:</label><div class="col-sm-8"><input type="text" required class="form-control" name="template"${edit ? ' value="Հարգելի {client}, շնորհավորում ենք Ձեր ծննդյան օրը։"' : ''}></div></div>`;
  const modal = (id, title, body, submitClass) => `<div id="${id}" class="modal fade form-horizontal settings-sms-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">${title}</h4></div><div class="modal-body">${body}</div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn ${submitClass} pull-right" type="button">Հաստատել</button></div></div></div></div>`;

  return `<div class="settings-sms-page">
    <section class="panel settings-sms-outer-panel">
      <div class="panel-body p-10">
        <div class="row">
          <div class="col-lg-12 pad_320">
            <section class="panel settings-sms-panel">
              <div class="panel-body pad_320 sms_">
                <div class="adv-table">
                  <button href="#addModal" data-toggle="modal" type="button" class="btn btn-success m-bot-10 settings-sms-add-btn"><img src="assets/img/icons/plusIcon.svg" alt=""> Ավելացնել շաբլոն</button>
                  <table class="table dynamic-table settings-sms-table">
                    <thead><tr><th>Անվանում</th><th>Տեքստ</th><th><i class="fa fa-cogs" aria-hidden="true"></i></th></tr></thead>
                    <tbody>${bodyRows}</tbody>
                  </table>
                </div>
              </div>
            </section>
          </div>
        </div>
      </div>
    </section>
    ${modal('addModal', 'Ավելացնել', formFields(false), 'btn-warning add-template')}
    ${modal('editModal', 'Փոփոխել', formFields(true), 'btn-warning edit-template')}
    ${modal('deleteModal', 'Ջնջել', '<p class="settings-sms-delete-text">Ջնջե՞լ շաբլոնը</p>', 'btn-warning delete-template')}
  </div>`;
}

function settingsMealTypesContent() {
  const rows = [
    ['Նախաճաշ'],
    ['Ճաշ'],
    ['Ընթրիք'],
    ['Բանկետ'],
    ['Առաքում'],
  ];
  const bodyRows = rows.map((row, index) => `<tr data-id="${index + 1}">
      <td>${row[0]}</td>
      <td><button type="button" class="btn btn-danger btn-xs inTableIconButton" data-toggle="modal" data-target="#deleteModal" value="${index + 1}"><img src="assets/img/icons/trash.svg" alt=""></button></td>
    </tr>`).join('');

  return `<div class="settings-meal-types-page">
    <div class="row settings-meal-types-row">
      <div class="col-xs-12">
        <section class="panel settings-meal-types-panel">
          <div class="panel-body">
            <button class="btn btn-success m-bot15 settings-meal-types-add-btn" data-toggle="modal" data-target="#addModal"><img src="assets/img/icons/plusIcon.svg" alt=""> Ավելացնել</button>
            <table class="table table-bordered mytable settings-meal-types-table">
              <thead><tr><th>Անվանում</th><th><i class="icon-cogs"></i></th></tr></thead>
              <tbody>${bodyRows}</tbody>
            </table>
          </div>
        </section>
      </div>
    </div>
    <div id="addModal" class="modal fade form-horizontal settings-meal-types-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Ավելացնել</h4></div><div class="modal-body"><div class="form-group"><label class="control-label col-sm-3">Սննդատեսակ:</label><div class="col-sm-9"><input type="text" required class="form-control" id="mealTypeTitle" name="mealTypeTitle"></div></div></div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-success pull-right" id="mealTypeBtn">Հաստատել</button></div></div></div></div>
    <div id="deleteModal" class="modal fade settings-meal-types-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h4 class="modal-title">Ջնջել սննդատեսակը</h4></div><div class="modal-body"><p>Ջնջե՞լ նշված սննդատեսակը</p></div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-danger pull-right" id="deleteMealType">Հաստատել</button></div></div></div></div>
  </div>`;
}

function settingsBranchContent() {
  const rows = [
    ['1', 'Կենտրոն', 'Երևան, Արամի 12', '+374 10 22 33 44'],
    ['2', 'Արաբկիր', 'Երևան, Կոմիտաս 41', '+374 11 55 66 77'],
    ['3', 'Առաքման կետ', 'Երևան, Արշակունյաց 15', '+374 99 10 20 30'],
  ];
  const bodyRows = rows.map(row => `<tr data-id="${row[0]}">
      ${row.map(cell => `<td>${cell}</td>`).join('')}
      <td class="cog_btns_td"><div class="flexBtns"><a href="#editModal" data-toggle="modal" class="btn btn-warning btn-xs edit-branch-btn inTableIconButton"><img src="assets/img/icons/pencil.svg" alt=""></a><a href="#deleteModal" data-toggle="modal" class="btn btn-danger btn-xs del-branch-modal inTableIconButton"><img src="assets/img/icons/trash.svg" alt=""></a></div></td>
    </tr>`).join('');
  const filters = Array.from({ length: 4 }).map(() => '<td><input type="text" class="form-control"></td>').join('');
  const switcher = name => `<div class="switch switch-square settings-branch-switch" data-on-label="Այո" data-off-label="Ոչ"><input type="checkbox" class="data switcher-item" name="${name}" value="1"></div>`;
  const textField = (label, name, value = '', type = 'text') => `<div class="form-group"><label>${label}</label><input type="${type}" class="form-control ${name}" name="${name}"${value ? ` value="${value}"` : ''}></div>`;
  const languageFields = (edit = false) => ['hy', 'ru', 'en'].map(locale => `<div class="branches" data-language="${locale}"><div class="form-group"><label>Name ${locale}</label><input type="text" required class="form-control name" name="name" value="${edit ? (locale === 'hy' ? 'Կենտրոն' : locale === 'ru' ? 'Центр' : 'Center') : ''}"></div><div class="form-group"><label>Address ${locale}</label><input type="text" class="form-control address" name="address" value="${edit ? (locale === 'hy' ? 'Երևան, Արամի 12' : locale === 'ru' ? 'Ереван, Арами 12' : 'Yerevan, Arami 12') : ''}"></div></div>`).join('');
  const integrationFields = (edit = false) => `<div class="settings-branch-section-title">Վճարային ինտեգրացիաներ</div>
    <div class="row settings-branch-switch-row"><div class="col-md-5"><label>Իդրամի նոր ինտեգրացիա</label></div><div class="col-md-7">${switcher('idramNewIntgration')}</div></div>
    <div class="row settings-branch-switch-row"><div class="col-md-5"><label>Իդրամի հասնալեիություն</label></div><div class="col-md-7">${switcher('idramAccess')}</div></div>
    <div class="row settings-branch-switch-row"><div class="col-md-5"><label>Իդրամ Cashback</label></div><div class="col-md-7">${switcher('idramCashback')}</div></div>
    ${textField('Իդրամ Url', 'idram-url', edit ? 'https://banking.idram.am/api' : '')}
    ${textField('Իդրամ ID', 'idram-id', edit ? '998001' : '', 'number')}
    ${textField('Իդրամ մասնաճյուղի ID', 'idram-branch-id', edit ? '12' : '', 'number')}
    ${textField('Իդրամ սեսսիայի բանալի', 'idram-session-id', edit ? 'SESSION-998' : '')}
    ${textField('Իդրամ հեռախոսահամար', 'idram-phone', edit ? '+37410223344' : '')}
    ${textField('Իդրամ թեյավճարի ID', 'idram-tip-id', edit ? 'TIP-12' : '')}
    <div class="row settings-branch-switch-row"><div class="col-md-5"><label>TelCell</label></div><div class="col-md-7">${switcher('telCellAccess')}</div></div>
    ${textField('TelCell User', 'telcell-user', edit ? 'smartrest-center' : '')}
    ${textField('TelCell Key', 'telcell-key', edit ? 'TELCELL-KEY-12' : '')}
    <div class="row settings-branch-switch-row"><div class="col-md-5"><label>InOne</label></div><div class="col-md-7">${switcher('inOneAccess')}</div></div>
    ${textField('InOne Brioche', 'inOne-brioche', edit ? 'BR-998' : '')}
    ${textField('InOne Password', 'inOne-password', edit ? 'inone-pass' : '')}
    <div class="row settings-branch-switch-row"><div class="col-md-5"><label>Evoca</label></div><div class="col-md-7">${switcher('evoca')}</div></div>
    ${textField('Evoca Branch ID', 'evoca-branch-id', edit ? 'EV-12' : '')}
    ${textField('Evoca URL', 'evoca-url', edit ? 'https://evoca.smartrest.am' : '')}
    ${textField('Evoca Token', 'evoca-token', edit ? 'EVOCA-TOKEN' : '')}
    <div class="row settings-branch-switch-row"><div class="col-md-5"><label>Tip Flag</label></div><div class="col-md-7">${switcher('tipFlag')}</div></div>`;
  const branchForm = (edit = false) => `${languageFields(edit)}<div class="phone">${textField('Phone', 'phone', edit ? '+37410223344' : '')}</div>${edit ? '<div class="form-group"><label>Laundry store</label><select name="laundry_store_id" class="form-control"><option value="0">- -</option><option>Հիմնական պահեստ</option></select></div>' : ''}${integrationFields(edit)}`;
  const modal = (id, title, body, submitClass) => `<div id="${id}" class="modal fade form-horizontal settings-branch-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">${title}</h4></div><div class="modal-body">${body}</div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-success ${submitClass} pull-right" type="button">Հաստատել</button></div></div></div></div>`;

  return `<div class="settings-branch-page">
    <div class="row settings-branch-row">
      <div class="col-xs-12">
        <section class="panel settings-branch-panel">
          <div class="panel-body">
            <button class="btn btn-success m-bot15 settings-branch-add-btn" data-toggle="modal" data-target="#addModal"><img src="assets/img/icons/plusIcon.svg" alt=""> Ավելացնել մասնաճյուղ</button>
            <div class="settings-branch-toolbar">
              <label>Ցույց տալ <select class="form-control input-sm"><option selected>10</option><option>25</option><option>50</option></select> գրառում</label>
              <a class="dt-button buttons-excel buttons-html5 ExcelButton" tabindex="0"><span><button class="btn btn-primary excelButton"><img src="assets/img/icons/ExcelLogo.svg" alt=""> Excel</button></span></a>
            </div>
            <table class="table table-striped table-bordered" id="companyGridTable">
              <thead>
                <tr><th>id</th><th>name</th><th>address</th><th>phone</th><th class="cogs"><i class="fa fa-cogs" aria-hidden="true"></i></th></tr>
                <tr class="filters">${filters}<td></td></tr>
              </thead>
              <tbody>${bodyRows}</tbody>
            </table>
            <div class="settings-branch-footer"><div>Ցուցադրված է 1-ից 3-ը 3 տողից</div><div class="settings-branch-pagination"><button class="btn btn-default btn-sm disabled">Նախորդը</button><button class="btn btn-danger btn-sm active">1</button><button class="btn btn-default btn-sm disabled">Հաջորդը</button></div></div>
          </div>
        </section>
      </div>
    </div>
    ${modal('addModal', 'Ավելացնել մասնաճյուղ', branchForm(false), 'add-branch-btn')}
    ${modal('editModal', 'Փոփոխել մասնաճյուղը', `${branchForm(true)}<input type="hidden" id="editRowId" value="1">`, 'edit-branch-submit')}
    <div id="deleteModal" class="modal fade settings-branch-modal settings-branch-delete-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-body"><p>Ջնջե՞լ մասնաճյուղը</p></div><input type="hidden" id="rowId" value="1"><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-danger del-branch-submit pull-right" id="deleteRoleSubmit"><img src="assets/img/icons/trash.svg" alt=""> Հաստատել</button></div></div></div></div>
    <input type="hidden" id="csrfToken" name="csrf_token" value="csrf-demo">
  </div>`;
}

function settingsUserBranchContent() {
  const rows = [
    ['admin', 'Կենտրոն  Երևան, Արամի 12'],
    ['cashier01', 'Արաբկիր  Երևան, Կոմիտաս 41'],
    ['waiter01', 'Առաքման կետ  Երևան, Արշակունյաց 15'],
    ['manager', 'Կենտրոն  Երևան, Արամի 12'],
  ];
  const bodyRows = rows.map((row, index) => `<tr data-id="${index + 1}">
      <td>${row[0]}</td>
      <td>${row[1]}</td>
      <td class="cog_btns_td"><div class="flexBtns"><a href="#deleteModal" data-toggle="modal" class="btn btn-danger btn-xs del-setting-modal inTableIconButton" data-id="${index + 1}"><img src="assets/img/icons/trash.svg" alt=""></a></div></td>
    </tr>`).join('');
  const filters = Array.from({ length: 2 }).map(() => '<td><input type="text" class="form-control"></td>').join('');

  return `<div class="settings-user-branch-page">
    <div class="row settings-user-branch-row">
      <div class="col-xs-12">
        <section class="panel settings-user-branch-panel">
          <div class="panel-body">
            <button class="btn btn-success m-bot15 settings-user-branch-add-btn" data-toggle="modal" data-target="#addModal"><img src="assets/img/icons/plusIcon.svg" alt=""> Ավելացնել</button>
            <div class="settings-user-branch-toolbar">
              <label>Ցույց տալ <select class="form-control input-sm"><option selected>10</option><option>25</option><option>50</option></select> գրառում</label>
              <a class="dt-button buttons-excel buttons-html5 ExcelButton" tabindex="0"><span><button class="btn btn-primary excelButton"><img src="assets/img/icons/ExcelLogo.svg" alt=""> Excel</button></span></a>
            </div>
            <table class="table table-striped table-bordered" id="companyGridTable">
              <thead>
                <tr><th>username</th><th>address</th><th class="cogs"><i class="fa fa-cogs" aria-hidden="true"></i></th></tr>
                <tr class="filters">${filters}<td></td></tr>
              </thead>
              <tbody>${bodyRows}</tbody>
            </table>
            <div class="settings-user-branch-footer"><div>Ցուցադրված է 1-ից 4-ը 4 տողից</div><div class="settings-user-branch-pagination"><button class="btn btn-default btn-sm disabled">Նախորդը</button><button class="btn btn-danger btn-sm active">1</button><button class="btn btn-default btn-sm disabled">Հաջորդը</button></div></div>
          </div>
        </section>
      </div>
    </div>
    <div id="addModal" class="modal fade form-horizontal settings-user-branch-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Ավելացնել</h4></div><div class="modal-body"><div class="form-group"><label class="control-label col-sm-4">Օգտատեր:</label><div class="col-sm-8"><select name="user" id="user" class="form-control"><option>admin</option><option>cashier01</option><option>waiter01</option><option>manager</option></select></div></div><div class="form-group"><label class="control-label col-sm-4">Մասնաճյուղ:</label><div class="col-sm-8"><select name="branch" id="branch" class="form-control"><option>Կենտրոն Երևան, Արամի 12</option><option>Արաբկիր Երևան, Կոմիտաս 41</option><option>Առաքման կետ Երևան, Արշակունյաց 15</option></select></div></div></div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-success add-user_setting-btn pull-right" type="button">Հաստատել</button></div></div></div></div>
    <div id="deleteModal" class="modal fade settings-user-branch-modal settings-user-branch-delete-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-body"><p>Ջնջե՞լ</p></div><input type="hidden" id="rowId" value="1"><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-danger del-setting-submit pull-right" id="deleteRoleSubmit"><img src="assets/img/icons/trash.svg" alt=""> Հաստատել</button></div></div></div></div>
    <input type="hidden" id="csrfToken" name="csrf_token" value="csrf-demo">
  </div>`;
}

function settingsArchiveDbContent() {
  return `<div class="settings-archive-db-page">
    <section class="panel settings-archive-db-panel">
      <div class="panel-body">
        <div class="settings-archive-db-warning">
          <p>Խնդրում ենք արխիվացնելուց առաջ զրոյացնել բոլոր <a target="_blank" href="cash.html">դրամարկղերի</a> մնացորդը և փակել բոլոր <a target="_blank" href="rooms-tables.html">սեղանները</a></p>
        </div>
        <button class="btn btn-danger delete-db settings-archive-db-btn" id="deleteDB" type="button" data-toggle="modal" data-target="#deleteDBModal" data-action="deleteDBModal">Արխիվացնել բազան</button>
      </div>
    </section>
    <div id="deleteDBModal" class="modal fade deleteDBModal settings-archive-db-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h4 class="modal-title">Խնդրում ենք հաստատել</h4></div><div class="modal-body"><p><span>Զգուշացում.</span> հաստատելուց հետո ամբողջ պատմությունը կջնջվի <span>անվերադարձ</span></p></div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-warning download-db-export" id="downloadDBExport" type="button">Ներբեռնել</button><button class="btn btn-danger pull-right delete-data-btn" id="deleteDBData" type="button">Զրոյացնել</button></div></div></div></div>
    <div id="fullscreenLoader" class="fullscreen-loader settings-archive-db-loader"><div class="settings-archive-db-loader-inner"><div class="spinner"></div><div class="loader-text" id="loaderExportText">Տվյալները ներբեռնվում են ․․․</div><div class="loader-text" id="loaderDeleteText">Տվյալները ջնջվում են ․․․</div></div></div>
  </div>`;
}

function adminSettingsContent() {
  const checkboxList = (items) => items.map(([id, label, checked = true]) => `<label for="${id}"><input name="${id}" id="${id}" type="checkbox"${checked ? ' checked' : ''}> ${label}</label>`).join('');
  const infoCheckboxList = (items) => items.map(([id, label, note, checked = true]) => `<div class="admin-settings-check-note"><label for="${id}"><input name="${id}" id="${id}" type="checkbox" class="checkbox-tumbler"${checked ? ' checked' : ''}> ${label}</label><p><i class="icon-info-sign"></i> ${note}</p></div>`).join('');
  const radioList = (name, items) => items.map(([id, value, label, checked = false]) => `<label for="${id}"><input name="${name}" id="${id}" value="${value}" type="radio"${checked ? ' checked' : ''}> ${label}</label>`).join('');

  const fastPin = infoCheckboxList([
    ['waiter_pin_popup', 'Արագ կցում', 'Սեղանին մատուցող կցելուց ծրագիրը ավտոմատ կերպով մատուցողին կկցի սեղանին։'],
    ['enter_after_pin', 'Արագ մուտք', 'Սեղմելով ազատ սեղանի վրա, միանգամից մուտք եք գործում սեղանի էջ։'],
    ['exit_after_print', 'Արագ վերադարձ', 'Սեղանի հաշիվը տպելուց հետո ծրագիրը ավտոմատ կերպով կվերադառնա սրահների էջ։'],
  ]);
  const moreFunctions = checkboxList([
    ['manri_hashvich', 'Մանրի վերադարձման հաշվիչ'],
    ['partqov_pagel', 'Սեղանը պարտքով փակել'],
    ['excel_details', 'Excel մանրամասն'],
    ['orva_menu', 'Օրվա մենյու'],
    ['need_fiscal', 'Տպել ՀԴՄ'],
    ['hdm_default', 'Հդմ֊ն սեղաններ սրահներում նախապես նշված'],
    ['hdm_default_fast_food', 'Հդմ֊ն արագ սնունդում նախապես նշված'],
    ['menu_item_barcode', 'Տեսականու բարկոդեր'],
    ['client_check_id', 'Սպասման համակարգ'],
    ['order_type_select', 'Պատվերի տեսակի ընտրություն'],
    ['product_sale', 'Ապրանքային զեղչ'],
    ['clients_count', 'Սեղաններ ֊ սրահներում հաճախորդների քանակ'],
    ['required_clients_count', 'Սեղաններում հաճախորդների քանակի դաշտը պարտադիր', false],
    ['required_clients_count_field_on_delivery_table', 'Առաքման սեղանում հաճախորդների քանակի դաշտը պարտադիր', false],
    ['required_tip', 'Թեյավճարի դաշտը պարտադիր', false],
    ['branch_list_drop_down_access', 'Մասնաճյուղերի ցուցակ'],
    ['initial_accept', 'Նախնական ընդունում'],
    ['check_comment', 'Կտրոնի նկարագրություն'],
    ['quantity_using_barcode', 'Կշեռքի բառկոդով աշխատելու հնարավորություն'],
    ['amount_discount', 'Գումարային զեղչ'],
    ['fast_food_customer_interface', 'Արագ սնունդի հաճախորդի ինտեռֆեյս', false],
    ['print_fast_food_hdm', 'Թաքցնել «Տպել ՀԴՄ անդորագիր» կոճակը', false],
    ['delivery_amount', 'Առաքման գումար', false],
    ['print_room_check', 'Տպել սենյակի կտրոնը', false],
    ['show_delivery_data_in_table', 'Ցուցադրել առաքման տվյալները սեղանի վրա', false],
    ['ai_generator_access', 'AI generator', false],
    ['ai_generator_files_access', 'AI generator files', false],
    ['download_db_export', 'Ներբեռնել բազան', false],
    ['menu_item_content_in_document', 'Տեսականու բաղադրության ներմուծում փաստաթղթերում', false],
  ]);
  const moreSystems = checkboxList([
    ['fast_food_new', 'Արագ սնունդ նոր համակարգ'],
    ['cookie_book', 'Խոհարարի էկրան'],
    ['fastfoodchecks', 'Արագ սնունդ պատրաստման վայրերի չեքեր'],
    ['sync_mootq', 'SyncMootq', false],
    ['mootq_cassa', 'Mootq cassa', false],
  ]);

  return `<div class="admin-settings-page">
    <div class="row admin-settings-row">
      <div class="col-lg-12 set">
        <section class="panel admin-settings-panel">
          <div class="panel-body">
            <div class="admin-settings-actions">
              <button class="btn btn-success create-store-balance-history" type="button">Create Balance History-(128)</button>
              <button class="btn btn-warning calculate-store-balance-history" type="button">Calculate Balance History</button>
              <button class="btn btn-danger remove-store-balance-history" type="button">Remove Balance History</button>
              <button class="btn btn-primary reset-calc-history" type="button">Reset Calculate History</button>
              <button class="btn btn-danger remove-actual-balance" type="button">Remove Actual Balance</button>
              <button class="btn btn-default check-order-content-id" type="button" data-action="checkOrderContentId">Order Content id check</button>
            </div>
            <section class="panel admin-settings-fix-panel">
              <div class="panel-body">
                <div class="form-group"><label for="backDateInput">Select date</label><input type="text" class="form-control dpd0" id="backDateInput" value="2026-07-11"></div>
                <div class="form-group"><label for="backTimeInput">Select time</label><input type="text" class="form-control tpt0" id="backTimeInput" value="12:00"></div>
                <div class="form-group admin-settings-fix-buttons"><button class="btn btn-default fix-price" type="button" data-action="fixPrice">24 fix-price</button><button class="btn btn-default fix-balance" type="button" data-action="fixBalance">8 fix-balance</button></div>
              </div>
            </section>
            <div class="admin-settings-entry-box">
              <p><i class="icon-info-sign"></i> Այս կոճակը ստեղծում է մուտքի փաստաթղթեր ըստ պահեստների, միջի հումքերի քանակը իր պահեստային մնացորդն է վերցնում, իսկ գինը՝ վերջին գնման ինքնարժեքը։</p>
              <button class="btn btn-primary create-entry-document" data-action="createEntryDocument" type="button">Ստեղծել մուտքի փաստաթուղթ</button>
              <button class="btn btn-danger reset-fifo-history" type="button" data-action="resetFifoHistory" data-toggle="modal" data-target="#confirm-modal">Ջնջել պահեստի պատմությունը</button>
            </div>
            <section class="admin-settings-section">
              <div class="admin-settings-section-title">Հիմնային պահեստային հաշվառում</div>
              <label class="admin-settings-switch"><input class="history-switch" type="checkbox" data-name="new_item_history_access" data-type="more_functions" checked><span></span></label>
            </section>
            <section class="admin-settings-section"><div class="admin-settings-section-title">Գործողությունների կրճատում</div><div class="checkboxes control-parent" id="fast_pin">${fastPin}</div></section>
            <section class="admin-settings-section"><div class="admin-settings-section-title">Լրացուցիչ ֆունկցիաներ</div><div class="checkboxes control-parent admin-settings-grid-checks" id="more_functions">${moreFunctions}</div></section>
            <section class="admin-settings-section"><div class="admin-settings-section-title">Առաքման ո՞ր տվյալները ցուցադրվեն սեղանի վրա</div><div class="radios" data-type="client_info_in_table">${radioList('client-info-in-table-radio', [['radio-005', 'name', 'Անուն', true], ['radio-006', 'phone', 'Հեռախոս'], ['radio-007', 'address', 'Հասցե']])}</div></section>
            <section class="admin-settings-section"><div class="admin-settings-section-title">Սրահների և սրահների ցուցադրման տարբերակ</div><div class="radios" data-type="halls_show_type">${radioList('sample-radio', [['radio-001', 'list', 'Ցուցակով(լռելյայն)', true], ['radio-002', 'plan', 'Ըստ հատակագծի'], ['radio-003', 'both', 'Երկուսն էլ']])}</div></section>
            <section class="admin-settings-section"><div class="admin-settings-section-title">Չեքերի տպման բաշխումը պրինտերների միջև կատարվի հետևյալ կերպ՝</div><div class="radios" data-type="printer_place">${radioList('sample-radio1', [['radio-11', 'profiles_checks_place', 'Այբենական կարգով'], ['radio-12', 'profiles_users', 'Ըստ օգտագործողի մուտքագրման'], ['radio-13', 'default', 'Լռելյայն', true]])}</div></section>
            <section class="admin-settings-section"><div class="admin-settings-section-title">Լրացուցիչ համակարգեր</div><div class="checkboxes control-parent admin-settings-grid-checks" id="more_systems" data-additional="more_systems">${moreSystems}<div class="mootq-print-before-arrival-setting"><label for="mootq_print_before_arrival_minutes">Mootq print before arrival minutes</label><input name="mootq_print_before_arrival_minutes" id="mootq_print_before_arrival_minutes" type="number" min="0" step="1" class="form-control js-profile-setting-number" value="0"><span class="help-block">Minutes before customer planned_arrival when Mootq checks should be printed. 0 means print immediately.</span></div></div></section>
          </div>
        </section>
      </div>
    </div>
    <div aria-hidden="true" role="dialog" id="myModal-5" class="modal fade admin-settings-modal"><div class="modal-dialog modal_left_and_top_padding40"><div class="modal-content"><div class="modal-header"><h4 class="modal-title">Ադմինիստրատիվ մուտք</h4></div><div class="modal-body"><form class="form-inline" role="form" onsubmit="return false"><p>Ադմինիստրատիվ մուտք գործելու դեպքում դուք կարող եք ջնջել կամ ավելացնել նոր պատվերներ, ինչպես նաև զեղչել սեղանի հաշիվը</p><div class="form-group"><label class="sr-only" for="exampleInputPassword5">Ադմինիստրատորի գաղտնաբառ</label><div class="numpad_main"><input tabindex="1" type="password" class="form-control sm-input" id="exampleInputPassword5" placeholder="Ադմինիստրատորի գաղտնաբառ"></div></div><input disabled id="admin_but" type="button" value="Հաստատել" class="btn btn-success" data-dismiss="modal"></form></div></div></div></div>
    <div id="confirm-modal" class="modal fade deleteModal admin-settings-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h4 class="modal-title">Խնդրում ենք հաստատել</h4></div><div class="modal-body"><p>Զգուշացում հաստատելուց հետո ամբողջ պահեստի պատմությունը կջնջվի</p></div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-danger pull-right remove-btn" id="confirm_but" disabled type="button">Հաստատել</button></div></div></div></div>
  </div>`;
}

function settingsFiscalContent() {
  const columns = [
    'id',
    'device_id',
    'group_id',
    'profile_nic',
    'ip_address',
    'ip_port',
    'secure_key',
    'hdm_protocol',
    'hdm_cashier',
    'hdm_cashier_password',
    'hdm_department',
    'comment',
    'title',
    'external_pos',
    'print_type',
  ];
  const rows = [
    ['12', '10001', '1', '998', '192.168.1.45', '9001', 'A8D3-99F1-SECURE', '2', '1001', 'cashier-998', '1', 'Հիմնական սարք', 'Կասսա', 'Ոչ', 'ՀԴՄ֊ով'],
    ['13', '10002', '1', '998', '192.168.1.46', '9002', 'B7C2-41AA-SECURE', '2', '1002', 'bar-998', '2', 'Բարի սարք', 'Բար', 'Այո', 'Տպիչով'],
    ['14', '10003', '2', '998', '10.10.0.12', '8080', 'DELIVERY-SECURE-09', '2', '1003', 'delivery-998', '3', 'Առաքման սարք', 'Առաքում', 'Ոչ', 'ՀԴՄ֊ով'],
  ];
  const filterCells = columns.map(() => '<td><input type="text" class="form-control"></td>').join('');
  const bodyRows = rows.map(row => `<tr>
      ${row.map(cell => `<td>${cell}</td>`).join('')}
      <td class="cog_btns_td"><div class="flexBtns inTableButtonsContainer"><a href="#deviceUsersModal" data-toggle="modal" class="btn btn-info btn-xs change-device-users inTableIconButton"><i class="fa fa-user"></i></a><a href="#editModal" data-toggle="modal" class="btn btn-warning btn-xs get-change-device inTableIconButton"><img src="assets/img/icons/pencil.svg" alt=""></a><a href="#deleteModal" data-toggle="modal" class="btn btn-danger btn-xs remove-device inTableIconButton"><img src="assets/img/icons/trash.svg" alt=""></a></div></td>
    </tr>`).join('');
  const select = (name, values, selected = '') => `<select class="form-control data ${name}" name="${name}">${values.map(([value, label]) => `<option value="${value}"${value === selected ? ' selected' : ''}>${label}</option>`).join('')}</select>`;
  const deviceFields = (edit = false) => [
    ['Device ID', edit ? 'device_id' : 'deviceId', 'number', edit ? '10001' : ''],
    ['Group ID', edit ? 'group_id' : 'groupId', 'number', edit ? '1' : ''],
    ['Title', 'title', 'text', edit ? 'Կասսա' : 'Կասսա'],
    ['HDM ip address', edit ? 'ip_address' : 'ipAddress', 'text', edit ? '192.168.1.45' : ''],
    ['HDM ip port', edit ? 'ip_port' : 'ipPort', 'text', edit ? '9001' : ''],
    ['HDM secure key', edit ? 'secure_key' : 'secureKey', 'text', edit ? 'A8D3-99F1-SECURE' : ''],
    ['Comment', 'comment', 'text', edit ? 'Հիմնական սարք' : ''],
    ['HDM protocol', edit ? 'hdm_protocol' : 'hdmProtocol', 'number', '2'],
    ['HDM cashier', edit ? 'hdm_cashier' : 'hdmCashier', 'text', edit ? '1001' : ''],
    ['HDM cashier password', edit ? 'hdm_cashier_password' : 'hdmCashierPassword', 'text', edit ? 'cashier-998' : ''],
    ['HDM department', edit ? 'hdm_department' : 'hdmDepartment', 'number', '1'],
  ].map(([label, name, type, value]) => `<div class="form-group"><label class="control-label col-sm-4">${label}:</label><div class="col-sm-8"><input type="${type}" required class="form-control data ${name}" name="${name}"${value ? ` value="${value}"` : ''}></div></div>`).join('');
  const deviceSelects = (edit = false) => `<div class="form-group"><label class="control-label col-sm-4">Արտաքին պոս:</label><div class="col-sm-8">${select(edit ? 'external_pos' : 'externalPos', [['0', 'no'], ['1', 'yes']], edit ? '0' : '')}</div></div><div class="form-group"><label class="control-label col-sm-4">Տպել:</label><div class="col-sm-8">${select(edit ? 'print_type' : 'printType', [['0', 'Հդմ֊ով'], ['1', 'Տպիչով']], edit ? '0' : '')}</div></div>`;
  const modal = (id, title, body, submitClass = 'btn-warning') => `<div id="${id}" class="modal fade form-horizontal settings-fiscal-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">${title}</h4></div><div class="modal-body">${body}</div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn ${submitClass} pull-right" type="button">Հաստատել</button></div></div></div></div>`;

  return `<div class="settings-fiscal-page">
    <div class="row store_padding settings-fiscal-row">
      <div class="settings-fiscal-info">
        <h4>License Key: FISCAL-998-2026-A1B2</h4>
        <h4>URL: https://fiscal.smartrest.am <button class="btn btn-warning btn-xs change-fiscal-settings" data-toggle="modal" data-target="#settingsModal"><img src="assets/img/icons/pencil.svg" alt=""></button></h4>
      </div>
      <div class="col-xs-12 clearfix settings-fiscal-add-row">
        <button class="btn btn-success m-bot15 pull-left settings-fiscal-add-btn" data-toggle="modal" data-target="#addModal"><i class="icon-plus"></i> Ավելացնել սարք</button>
      </div>
      <div class="panel settings-fiscal-panel">
        <div class="panel-body">
          <div class="settings-fiscal-toolbar">
            <label>Ցույց տալ <select class="form-control input-sm"><option selected>10</option><option>25</option><option>50</option></select> գրառում</label>
            <a class="dt-button buttons-excel buttons-html5 ExcelButton" tabindex="0"><span><button class="btn btn-primary excelButton"><img src="assets/img/icons/ExcelLogo.svg" alt=""> Excel</button></span></a>
          </div>
          <table class="table table-striped table-bordered" id="companyGridTable">
            <thead>
              <tr>${columns.map(column => `<th>${column}</th>`).join('')}<th class="cogs"><i class="fa fa-cogs" aria-hidden="true"></i></th></tr>
              <tr class="filters">${filterCells}<td></td></tr>
            </thead>
            <tbody>${bodyRows}</tbody>
          </table>
          <div class="settings-fiscal-footer"><div>Ցուցադրված է 1-ից 3-ը 3 տողից</div><div class="settings-fiscal-pagination"><button class="btn btn-default btn-sm disabled">Նախորդը</button><button class="btn btn-danger btn-sm active">1</button><button class="btn btn-default btn-sm disabled">Հաջորդը</button></div></div>
        </div>
      </div>
    </div>
    ${modal('settingsModal', 'Fiscal settings', '<div class="form-group"><label>License Key</label><input type="text" class="form-control license-key" value="FISCAL-998-2026-A1B2"></div><div class="form-group"><label>URL</label><input type="text" class="form-control url" value="https://fiscal.smartrest.am"></div><div class="form-group"><label>Update url</label><input type="text" class="form-control update_url" value="https://fiscal.smartrest.am/update"></div>', 'btn-success')}
    <div id="deviceUsersModal" class="modal fade form-horizontal settings-fiscal-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Device users</h4></div><div class="modal-body"><div class="device-users-container"><div class="form-group col-md-11"><select class="form-control" name="user_id"><option>admin</option><option>cashier01</option><option>waiter01</option></select></div><button class="btn btn-sm btn-success add-device-user" type="button"><i class="icon-plus"></i></button></div><table class="table table-bordered"><thead><tr><th>Username</th><th><i class="icon-trash"></i></th></tr></thead><tbody><tr><td>cashier01</td><td><button class="btn btn-danger btn-xs inTableIconButton"><img src="assets/img/icons/trash.svg" alt=""></button></td></tr></tbody></table><input type="hidden" id="deviceId"></div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button></div></div></div></div>
    ${modal('addModal', 'Ավելացնել', `${deviceFields(false)}${deviceSelects(false)}<input type="hidden" class="form-control data" name="profileNic" value="998">`)}
    ${modal('editModal', 'Ավելացնել', `${deviceFields(true)}${deviceSelects(true)}`)}
    <div id="deleteModal" class="modal fade deleteModal settings-fiscal-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h4 class="modal-title">Ջնջել սարքը</h4></div><div class="modal-body"><p>Ջնջե՞լ նշված սարքը</p></div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-danger pull-right remove-device-btn" type="button">Հաստատել</button></div></div></div></div>
    <input type="hidden" id="row"><input type="hidden" id="csrfToken" name="csrf_token" value="csrf-demo">
  </div>`;
}

function settingsHdmContent() {
  const rows = [
    ['998', '192.168.1.45', '9001', 'A8D3-99F1-SECURE', '2', '1001', 'cashier-998', '1', 'Հիմնական ՀԴՄ'],
    ['998', '192.168.1.46', '9002', 'B7C2-41AA-SECURE', '2', '1002', 'bar-998', '2', 'Բարի ՀԴՄ'],
    ['998', '10.10.0.12', '8080', 'DELIVERY-SECURE-09', '2', '1003', 'delivery-998', '3', 'Առաքման ՀԴՄ'],
  ];
  const bodyRows = rows.map((row, index) => `<tr data-id="${index + 1}">
      <td class="dt-control-cell"></td><td class="dt-control-cell"></td><td class="dt-control-cell"></td><td class="dt-control-cell"></td>
      ${row.map(cell => `<td>${cell}</td>`).join('')}
      <td class="cog_btns_td"><div class="client_icons"><button class="btn btn-warning btn-xs btn_edit inTableIconButton" data-toggle="modal" data-target="#editClient"><i class="icon-pencil"></i></button></div></td>
    </tr>`).join('');
  const filters = Array.from({ length: 13 }).map((_, index) => index < 4 ? '<td class="dt-control-cell"></td>' : '<td><input type="text" class="form-control"></td>').join('');
  const fieldRows = (edit = false) => [
    ['HDM ip address', 'hdm_ip-address', 'text', edit ? '192.168.1.45' : ''],
    ['HDM ip port', 'hdm_ip-port', 'text', edit ? '9001' : ''],
    ['HDM secure key', 'hdm_secure-key', 'text', edit ? 'A8D3-99F1-SECURE' : ''],
    ['Comment', 'comment', 'text', edit ? 'Հիմնական ՀԴՄ' : ''],
    ['HDM protocol', 'hdm-protocol', 'number', edit ? '2' : '2'],
    ['HDM cashier', 'hdm-cashier', 'text', edit ? '1001' : ''],
    ['HDM cashier password', 'hdm-cashier_password', 'text', edit ? 'cashier-998' : ''],
    ['HDM department', 'hdm-department', 'number', edit ? '1' : '1'],
  ].map(([label, name, type, value]) => `<div class="form-group"><label class="control-label col-sm-4">${label}:</label><div class="col-sm-8"><input type="${type}" required class="form-control data" name="${name}"${value ? ` value="${value}"` : ''}></div></div>`).join('');
  const modal = (id, title, body) => `<div id="${id}" class="modal fade form-horizontal editModal settings-hdm-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">${title}</h4></div><div class="modal-body">${body}</div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-warning pull-right" type="button" data-table="hdm_settings">Հաստատել</button></div></div></div></div>`;

  return `<div class="settings-hdm-page">
    <section class="panel settings-hdm-outer-panel">
      <div class="panel-body">
        <div class="row">
          <div class="col-lg-12 pad_320">
            <section class="panel settings-hdm-panel">
              <div class="panel-body pad_320">
                <div class="adv-table">
                  <button href="#addClient" data-toggle="modal" type="button" class="btn btn-success m-bot-10 settings-hdm-add-btn"><img src="assets/img/icons/plusIcon.svg" alt=""> Ավելացնել լիցենզիա</button>
                  <div class="settings-hdm-toolbar">
                    <label>Ցույց տալ <select class="form-control input-sm"><option selected>10</option><option>25</option><option>50</option></select> գրառում</label>
                    <a class="dt-button buttons-excel buttons-html5 ExcelButton" tabindex="0"><span><button class="btn btn-primary excelButton" type="button"><img src="assets/img/icons/ExcelLogo.svg" alt=""> Excel</button></span></a>
                  </div>
                  <table class="table table-striped table-bordered mytable" id="clientGridTable">
                    <thead>
                      <tr><th class="dt-control-cell"></th><th class="dt-control-cell"></th><th class="dt-control-cell"></th><th class="dt-control-cell"></th><th>Profile Nic</th><th>IP address</th><th>IP port</th><th>Secure Key</th><th>Protocol</th><th>Cashier</th><th>Cashier password</th><th>Cashier department</th><th>Comment</th><th class="cogs"><i class="icon-edit"></i></th></tr>
                      <tr class="filters">${filters}<td></td></tr>
                    </thead>
                    <tbody>${bodyRows}</tbody>
                  </table>
                  <div class="settings-hdm-footer"><div>Ցուցադրված է 1-ից 3-ը 3 տողից</div><div class="settings-hdm-pagination"><button class="btn btn-default btn-sm disabled">Նախորդը</button><button class="btn btn-danger btn-sm active">1</button><button class="btn btn-default btn-sm disabled">Հաջորդը</button></div></div>
                </div>
              </div>
            </section>
          </div>
        </div>
      </div>
    </section>
    ${modal('addClient', 'Ավելացնել', `${fieldRows(false)}<input type="hidden" class="form-control data" name="profile_nic" value="998">`)}
    ${modal('editClient', 'Փոփոխել', fieldRows(true))}
  </div>`;
}

function settingsHdmLicenseContent() {
  const rows = [
    ['998', 'https://taxservice.smartrest.local/api', 'LIC-998-2026-A1B2', 'Հիմնական մասնաճյուղ'],
    ['998', 'https://cloud2.rst.am/hdm', 'LIC-998-BAR-4431', 'Բար / երկրորդ սարք'],
    ['998', 'https://fiscal.smartrest.am', 'LIC-998-DELIVERY-09', 'Առաքման ՀԴՄ'],
  ];
  const bodyRows = rows.map((row, index) => `<tr data-id="${index + 1}">
      ${row.map(cell => `<td>${cell}</td>`).join('')}
      <td class="cog_btns_td"><div class="flexBtns"><a href="#editClient" data-toggle="modal" class="btn btn-warning btn-xs btn_edit inTableIconButton"><i class="fa fa-edit"></i></a></div></td>
    </tr>`).join('');
  const filters = Array.from({ length: 4 }).map(() => '<td><input type="text" class="form-control"></td>').join('');
  const formFields = (edit = false) => `<div class="form-group"><label class="control-label col-sm-4">URl:</label><div class="col-sm-8"><input type="text" required class="form-control data" name="url"${edit ? ' value="https://taxservice.smartrest.local/api"' : ''}></div></div><div class="form-group"><label class="control-label col-sm-4">Comment:</label><div class="col-sm-8"><input type="text" required class="form-control data" name="comment"${edit ? ' value="Հիմնական մասնաճյուղ"' : ''}></div></div><div class="form-group"><label class="control-label col-sm-4">License key:</label><div class="col-sm-8"><input type="text" required class="form-control data" name="license_key"${edit ? ' value="LIC-998-2026-A1B2"' : ''}></div></div>${edit ? '' : '<input type="hidden" class="form-control data" name="profile_nic" value="998">'}`;
  const modal = (id, title, body) => `<div id="${id}" class="modal fade form-horizontal editModal settings-hdm-license-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">${title}</h4></div><div class="modal-body">${body}</div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-warning pull-right" type="button" data-table="hdm_license">Հաստատել</button></div></div></div></div>`;

  return `<div class="settings-hdm-license-page">
    <section class="panel settings-hdm-license-outer-panel">
      <div class="panel-body">
        <div class="row">
          <div class="col-lg-12 pad_320">
            <section class="panel settings-hdm-license-panel">
              <div class="panel-body pad_320">
                <div class="adv-table">
                  <button href="#addClient" data-toggle="modal" type="button" class="btn btn-success m-bot-10 settings-hdm-license-add-btn"><img src="assets/img/icons/plusIcon.svg" alt=""> Ավելացնել լիցենզիա</button><br>
                  <div class="settings-hdm-license-toolbar">
                    <label>Ցույց տալ <select class="form-control input-sm"><option selected>10</option><option>25</option><option>50</option></select> գրառում</label>
                    <a class="dt-button buttons-excel buttons-html5 ExcelButton" tabindex="0"><span><button class="btn btn-primary excelButton" type="button"><img src="assets/img/icons/ExcelLogo.svg" alt=""> Excel</button></span></a>
                  </div>
                  <table class="table table-striped table-bordered" id="clientGridTable">
                    <thead>
                      <tr><th>profile_nic</th><th>url</th><th>license_key</th><th>comment</th><th class="cogs"><i class="fa fa-cogs" aria-hidden="true"></i></th></tr>
                      <tr class="filters">${filters}<td></td></tr>
                    </thead>
                    <tbody>${bodyRows}</tbody>
                  </table>
                  <div class="settings-hdm-license-footer"><div>Ցուցադրված է 1-ից 3-ը 3 տողից</div><div class="settings-hdm-license-pagination"><button class="btn btn-default btn-sm disabled">Նախորդը</button><button class="btn btn-danger btn-sm active">1</button><button class="btn btn-default btn-sm disabled">Հաջորդը</button></div></div>
                </div>
              </div>
            </section>
          </div>
        </div>
      </div>
    </section>
    ${modal('addClient', 'Ավելացնել', formFields(false))}
    ${modal('editClient', 'Փոփոխել', formFields(true))}
  </div>`;
}

function clientContent() {
  return `<div class="client-screen"><div class="row"><div class="col-md-7"><h1>Բարի գալուստ SMARTREST</h1><p style="font-size:22px;color:#b7c0cf">Ձեր պատվերը պատրաստվում է</p><div class="queue-number">A-128</div><button class="btn btn-success btn-lg" data-toggle="modal" data-target="#welcomeClientModal">Welcome modal</button> <button class="btn btn-warning btn-lg" data-toggle="modal" data-target="#waitCheckClientModal">Wait check</button></div><div class="col-md-5"><img src="assets/img/icons/clientWelcome.png" alt="" style="max-width:100%;margin-top:35px"></div></div></div>`;
}

function incomingOrdersContent() {
  const orders = [
    { id: 128, status: 'success', color: 'light-green', time: '14:42', date: '2026-07-02 14:42:18', table: 'Սեղան 4', items: [['Սեզար աղցան', 2, 2900], ['Լատե', 1, 1200], ['Խնձորի ֆրեշ', 2, 900]], comment: 'Առանց սոխի, խնդրում ենք բերել արագ' },
    { id: 129, status: 'warning', color: 'yellow', time: '14:36', date: '2026-07-02 14:36:05', table: 'Սեղան 8', items: [['Բուրգեր հավի մսով', 1, 2200], ['Կարտոֆիլ ֆրի', 2, 900]], comment: 'Սեղանի մոտ վճարում' },
    { id: 130, status: 'danger', color: 'red', time: '14:20', date: '2026-07-02 14:20:43', table: 'Առաքում #27', items: [['Պիցցա Մարգարիտա', 1, 3400], ['Կոլա', 2, 600]], comment: 'Հասցե՝ Արամի 12' },
    { id: 131, status: 'success', color: 'light-green', time: '13:58', date: '2026-07-02 13:58:11', table: 'Սեղան 2', items: [['Սուրճ Ամերիկանո', 2, 800], ['Թխվածք', 1, 1500]], comment: '' }
  ];

  const timeline = orders.map((order, index) => {
    const alt = order.status === 'warning' ? ' alt' : '';
    const arrow = order.status === 'warning' ? 'arrow-alt' : 'arrow';
    const total = order.items.reduce((sum, item) => sum + (item[1] * item[2]), 0);
    return `<article class="timeline-item${alt}">
      <div class="timeline-desk">
        <div class="panel bg-${order.status}">
          <div class="panel-body">
            <span class="${arrow} border-${order.status}"></span>
            <span class="timeline-icon ${order.color}"></span>
            <span class="timeline-date">${order.time}</span>
            <div class="pull-left">
              <h1><i class="icon-time"></i> ${order.date}</h1>
              <h1 class="text-primary"><i class="icon-user"></i> ${order.table}</h1>
              <h5 class="incoming-order-summary"><span>${order.items.length}</span> ապրանք / <span>${total}</span> դրամ</h5>
            </div>
            <div class="pull-right">
              <button class="btn btn-info btn-xs btn-block btn-more incoming-order-more" data-order="${index}" data-toggle="modal" data-target="#getModal">Մանրամասն</button>
            </div>
          </div>
        </div>
      </div>
    </article>`;
  }).join('');

  const modalRows = orders[0].items.map((item, index) => `<tr data-id="${index + 1}"><td>${index + 1}</td><td>${item[0]}</td><td>${item[1]}</td><td>${item[2]}</td><td>${item[1] * item[2]}</td></tr>`).join('');

  return `<div class="row incoming-orders-page">
    <div class="col-lg-12">
      <section class="panel">
        <div class="panel-heading yellow">
          <a class="btn btn-success pull-right btn-lg" href="client-screen.html">Հաճախորդի էջ</a>
          <div class="clearfix"></div>
        </div>
        <div class="panel-body">
          <div class="timeline">${timeline}</div>
          <div class="clearfix">&nbsp;</div>
        </div>
      </section>
    </div>
  </div>
  <div class="modal fade bs-example-modal-lg incoming-order-modal" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" id="getModal">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-body bg-grey">
          <div class="incoming-order-detail">
            <div class="row">
              <div class="col-sm-6"><h3><i class="icon-user"></i> Սեղան 4</h3><p class="text-muted">2026-07-02 14:42:18</p></div>
              <div class="col-sm-6 text-right"><h1 class="count">8200</h1><p>դրամ</p></div>
            </div>
            <table class="table table-bordered table-striped" id="sortable">
              <thead><tr><th>#</th><th>Անվանում</th><th>Քանակ</th><th>Գին</th><th>Ընդհանուր</th></tr></thead>
              <tbody>${modalRows}</tbody>
            </table>
            <textarea class="form-control" id="delivery-comment" rows="3" placeholder="Մեկնաբանություն">Առանց սոխի, խնդրում ենք բերել արագ</textarea>
          </div>
        </div>
        <div class="panel-body">
          <div class="btn-group btn-group-justified">
            <a class="btn btn-danger" id="delivery-danger">Մերժել</a>
            <a class="btn btn-info" aria-hidden="true" data-dismiss="modal">Փակել</a>
            <a class="btn btn-success" id="delivery-success">Ընդունել</a>
          </div>
        </div>
      </div>
    </div>
  </div>`;
}

function aiGeneratorContent() {
  return `<div class="ai-page">
  <div class="ai-shell">
    <div class="ai-welcome" id="aiWelcome">
      <h2 class="ai-title">Բարի գալուստ SmartRest AI</h2>
      <div class="ai-subtitle">Ինչո՞վ կարող եմ օգնել</div>
      <div class="ai-prompt">
        <input class="form-control" id="aiWelcomeInput" type="text" placeholder="Գրել հարցը" autocomplete="off">
        <button class="ai-icon-btn" id="aiWelcomeSend" type="button" aria-label="Send">
          <i class="fa fa-play"></i>
        </button>
      </div>
    </div>

    <div class="ai-chat" id="aiChat">
      <div class="ai-chat-shell">
        <aside class="ai-history" id="aiHistory">
          <div class="ai-history-head">
            <div>
              <div class="ai-history-title">Chats</div>
              <div class="ai-history-subtitle" id="aiHistoryMeta">Recent conversations</div>
            </div>
            <div class="ai-history-head-actions">
              <button class="ai-history-btn" id="aiHistoryRefresh" type="button" title="Refresh" aria-label="Refresh">
                <i class="fa fa-refresh"></i>
              </button>
              <button class="ai-history-btn ai-history-btn--primary" id="aiNewChatBtn" type="button" title="New chat" aria-label="New chat">
                <i class="fa fa-plus"></i>
              </button>
            </div>
          </div>
          <div class="ai-history-list" id="aiHistoryList"></div>
          <div class="ai-history-empty" id="aiHistoryEmpty" style="display:none">
            <i class="fa fa-comments-o"></i>
            <span>No chats yet</span>
          </div>
        </aside>
        <div class="ai-chat-main">
          <div class="ai-messages" id="aiMessages" aria-live="polite"></div>
          <div class="ai-chat-feedback" id="aiChatFeedback" style="display:none"></div>
          <div class="ai-composer">
            <div class="ai-attachments ai-pending" id="aiPending" style="display:none"></div>
            <div class="ai-composer-row">
              <input class="ai-file-input" id="aiFiles" type="file" multiple>
              <textarea class="form-control" id="aiChatInput" rows="3" placeholder="Ձեր հարցը"></textarea>
            </div>
            <div class="ai-composer-actions" aria-label="Actions">
              <button class="ai-icon-btn" id="aiAttachBtn" type="button" aria-label="Attach">
                <i class="fa fa-plus"></i>
              </button>
              <button class="ai-icon-btn" id="aiSendBtn" type="button" aria-label="Send">
                <i class="fa fa-play"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<input id="chatId" type="text" hidden="hidden">
<div class="ai-feedback-modal" id="aiChatFeedbackModal" aria-hidden="true" style="display:none">
  <div class="ai-feedback-modal__backdrop" data-chat-feedback-close></div>
  <div class="ai-feedback-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="aiChatFeedbackModalTitle">
    <div class="ai-feedback-modal__head">
      <div>
        <div class="ai-feedback-modal__title" id="aiChatFeedbackModalTitle">Chat feedback</div>
        <div class="ai-feedback-modal__subtitle">Tell us what was helpful or what should improve.</div>
      </div>
      <button type="button" class="ai-feedback-modal__close" data-chat-feedback-close aria-label="Close">
        <i class="fa fa-times"></i>
      </button>
    </div>
    <div class="ai-feedback-modal__body">
      <textarea class="form-control" id="aiChatFeedbackInput" rows="5" maxlength="800" placeholder="Write feedback"></textarea>
      <div class="ai-feedback-modal__status ai-chat-feedback-status" id="aiChatFeedbackStatus"></div>
    </div>
    <div class="ai-feedback-modal__actions">
      <button type="button" class="btn btn-default" data-chat-feedback-close>Cancel</button>
      <button type="button" class="ai-chat-feedback-send" id="aiChatFeedbackSubmit" title="Send feedback" aria-label="Send feedback">Send feedback</button>
    </div>
  </div>
</div>`;
}

function hiddenContent() {
  return `<section class="panel"><header class="panel-heading">Hidden screens and popups</header><div class="panel-body"><div class="hidden-state"><strong>Transform alert:</strong> warning state above room table grid when moving table/items.</div><div class="hidden-state"><strong>Admin access:</strong> password inputs in header report dropdown and order actions.</div><div class="hidden-state"><strong>Client screen modals:</strong> welcome, wait check, thanks states.</div><div class="modal-demo-row"><button class="btn btn-danger" data-toggle="modal" data-target="#dayEndModal">Օրվա վերջ</button><button class="btn btn-info" data-toggle="modal" data-target="#waiterPinModal">Մատուցողի ակտիվացում</button><button class="btn btn-warning" data-toggle="modal" data-target="#discountModal">Զեղչ</button><button class="btn btn-primary" data-toggle="modal" data-target="#clientModal">Հաճախորդ</button><button class="btn btn-success" data-toggle="modal" data-target="#addModal">Ավելացնել</button><button class="btn btn-danger" data-toggle="modal" data-target="#balanceErrorModal">Անբավարար մնացորդ</button></div></div></section>`;
}

function menuContent() {
  const places = [
    { id: 1, title: 'Խոհանոց', icon: 'icon-food', color: '#ec7b6a', store: 'Զաքյան', groups: ['Աղցաններ', 'Ապուրներ', 'Տաք ուտեստներ', 'Պաստա', 'Նախուտեստներ'] },
    { id: 2, title: 'Բար', icon: 'icon-glass', color: '#5fa8d3', store: 'Rosemary', groups: ['Սուրճ', 'Թեյ', 'Կոկտեյլներ', 'Լիմոնադներ', 'Գինի'] },
    { id: 3, title: 'Հացաբուլկեղեն', icon: 'icon-coffee', color: '#a7c798', store: 'Արտադրամաս', groups: ['Դեսերտներ', 'Տորթեր', 'Խմորեղեն', 'Պաղպաղակ'] },
  ];

  const placeCards = places.map(place => `<div id="menuPlace${place.id}" class="col-sm-4 col-xs-12 sortable_children_place" data-item="${place.id}" data-sort-order="${place.id}">
    <aside class="profile-nav alt menu-place-card">
      <section class="panel">
        <div class="user-heading alt" style="background-color:${place.color}">
          <h1 class="text-center"><i id="menuPlaceIcon${place.id}" class="${place.icon}"></i><span class="titleText"> ${place.title}</span></h1>
          <p class="text-center menu_it">
            <button type="button" class="btn btn-warning btn-xs btn_edit inTableIconTextButton editMenuPlace" data-toggle="modal" data-target="#menuEditModal" value="${place.id}">
              <img src="assets/img/icons/pencil.svg" alt=""> Փոփոխել
            </button>
            <button type="button" class="btn btn-danger btn-xs btn_delete inTableIconTextButton removeMenuPlace" data-toggle="modal" data-target="#menuDeleteModal" value="${place.id}">
              <img src="assets/img/icons/trash.svg" alt=""> Ջնջել
            </button>
            <button type="button" class="btn btn-default btn-xs btn_copy inTableIconTextButton" data-toast="Պատճենվեց">
              <i class="icon-copy"></i> Copy
            </button>
            <button type="button" class="btn btn-default btn-xs btn_excell inTableIconTextButton">
              <i class="icon-cogs"></i> Արտածել Excel
            </button>
          </p>
        </div>
        <button type="button" data-place="${place.id}" class="btn btn-success btn-block addGroupBtn" data-toggle="modal" data-target="#menuAddGroupModal">
          <i class="icon-plus"></i> Ավելացնել բաժին
        </button>
        <ul class="nav nav-pills nav-stacked sortable menu-groups-list" data-table="menu_group">
          ${place.groups.map((group, groupIndex) => `<li class="sortable_children" data-item="${place.id}${groupIndex + 1}">
            <a class="col-xs-9" href="menu.html?id=${place.id}${groupIndex + 1}">${group}</a>
            <div class="text-center col-xs-3 p-t-10">
              <div class="inTableButtonsContainer">
                <button type="button" class="btn btn-warning btn-xs btn_edit inTableIconButton" data-toggle="modal" data-target="#menuEditGroupModal" value="${place.id}${groupIndex + 1}">
                  <img src="assets/img/icons/pencil.svg" alt="">
                </button>
                <button type="button" class="btn btn-danger btn-xs btn_delete inTableIconButton" data-toggle="modal" data-target="#menuDeleteGroupModal" value="${place.id}${groupIndex + 1}">
                  <img src="assets/img/icons/trash.svg" alt="">
                </button>
                <button type="button" class="btn btn-default btn-xs inTableIconButton" disabled><i class="icon-move"></i></button>
              </div>
            </div>
          </li>`).join('')}
        </ul>
      </section>
    </aside>
  </div>`).join('');

  const groupTabs = ['Ոզնու խմիչք', 'Գինի Բաժակով', 'Բուսական կաթով ըմպելիքներ', 'Ֆրեշ,ջէյք', 'Կոկտեյլներ', 'Շոկոլադե ըմպելիքներ', 'Գարեջուր', 'Տաք և Սառը ըմպելիքներ', 'Գինի', 'Թեյ', 'Սուրճեր', 'Ջրեր'];
  const menuItems = [
    { id: 171, groupId: 11, name: 'Կաթնային շոկոլադ', price: 1500, image: 'icon-food.png', checkPlace: 'ԲԱՐ', cost: 450, income: '30', total: '450.0000', ingredients: [
      ['Հումք', 'Քանակ', 'Ընդհ. ինքնարժեք', 'Վերջին ձեռքբերման ինքնարժեք', 'Պահեստ'],
      ['Կաթ', '0.200 Լիտր', '145.000', '145.000', 'Զաքյան'],
      ['Շոկոլադ', '0.050 Կիլոգրամ', '305.000', '305.000', 'Զաքյան'],
    ] },
    { id: 172, groupId: 11, name: 'Բանանի կակաո', price: 2700, image: 'icon-food.png', checkPlace: 'ԲԱՐ', cost: 795, income: '29.4', total: '794.9000', ingredients: [
      ['Հումք', 'Քանակ', 'Ընդհ. ինքնարժեք', 'Վերջին ձեռքբերման ինքնարժեք', 'Պահեստ'],
      ['Բանան', '0.200 Կիլոգրամ', '210.000', '210.000', 'Զաքյան'],
      ['Կաթ', '0.250 Լիտր', '180.000', '180.000', 'Զաքյան'],
      ['Կակաո', '0.040 Կիլոգրամ', '405.000', '405.000', 'Զաքյան'],
    ] },
    { id: 174, groupId: 11, name: 'Կակաո', price: 1500, image: 'icon-food.png', checkPlace: 'ԲԱՐ', cost: 420, income: '28', total: '420.0000', ingredients: [
      ['Հումք', 'Քանակ', 'Ընդհ. ինքնարժեք', 'Վերջին ձեռքբերման ինքնարժեք', 'Պահեստ'],
      ['Կաթ', '0.250 Լիտր', '180.000', '180.000', 'Զաքյան'],
      ['Կակաո', '0.030 Կիլոգրամ', '240.000', '240.000', 'Զաքյան'],
    ] },
    { id: 173, aliases: [658], groupId: 11, name: 'Կինդեր կակաո', price: 2800, image: 'icon-food.png', checkPlace: 'ԲԱՐ', cost: 832, income: '29.7', total: '831.5899', ingredients: [
      ['Հումք', 'Քանակ', 'Ընդհ. ինքնարժեք', 'Վերջին ձեռքբերման ինքնարժեք', 'Պահեստ'],
      ['Կլար քառ', '0.250 Լիտր', '225.000', '225.000', 'Զաքյան'],
      ['Կինդեր', '2.000 Հատ', '177.500', '212.500', 'Զաքյան'],
      ['Տաք շոկոլադ', '0.028 Կիլոգրամ', '280.000', '280.000', 'Զաքյան'],
      ['Սերուցք հարած President', '0.030 Լիտր', '102.000', '102.000', 'Զաքյան'],
      ['Սուրճ Տոպինգ', '0.010 Լիտր', '47.090', '47.090', 'Զաքյան'],
    ] },
    { id: 353, groupId: 11, name: 'Բաունտի կակաո', price: 2700, image: 'icon-food.png', checkPlace: 'ԲԱՐ', cost: 803, income: '29.7', total: '803.0000', freezed: true, ingredients: [
      ['Հումք', 'Քանակ', 'Ընդհ. ինքնարժեք', 'Վերջին ձեռքբերման ինքնարժեք', 'Պահեստ'],
      ['Կաթ', '0.250 Լիտր', '180.000', '180.000', 'Զաքյան'],
      ['Բաունտի', '1.000 Հատ', '210.000', '210.000', 'Զաքյան'],
      ['Տաք շոկոլադ', '0.028 Կիլոգրամ', '280.000', '280.000', 'Զաքյան'],
      ['Կոկոսի փոշի', '0.020 Կիլոգրամ', '85.000', '85.000', 'Զաքյան'],
      ['Սուրճ Տոպինգ', '0.010 Լիտր', '48.000', '48.000', 'Զաքյան'],
    ] },
    { id: 175, groupId: 11, name: 'Օրեո կակաո', price: 2300, image: 'icon-food.png', checkPlace: 'ԲԱՐ', cost: 690, income: '30', total: '690.0000', ingredients: [
      ['Հումք', 'Քանակ', 'Ընդհ. ինքնարժեք', 'Վերջին ձեռքբերման ինքնարժեք', 'Պահեստ'],
      ['Օրեո', '2.000 Հատ', '260.000', '260.000', 'Զաքյան'],
      ['Կաթ', '0.250 Լիտր', '180.000', '180.000', 'Զաքյան'],
      ['Կակաո', '0.030 Կիլոգրամ', '250.000', '250.000', 'Զաքյան'],
    ] },
    { id: 176, groupId: 11, name: 'Շոկոլադե սուրճեր', price: 1900, image: 'icon-food.png', checkPlace: 'ԲԱՐ', cost: 560, income: '29.5', total: '560.0000', ingredients: [
      ['Հումք', 'Քանակ', 'Ընդհ. ինքնարժեք', 'Վերջին ձեռքբերման ինքնարժեք', 'Պահեստ'],
      ['Սուրճ', '0.020 Կիլոգրամ', '220.000', '220.000', 'Զաքյան'],
      ['Շոկոլադ', '0.050 Կիլոգրամ', '340.000', '340.000', 'Զաքյան'],
    ] },
    { id: 177, groupId: 11, name: 'Ջինջեր շոկոլադ', price: 1800, image: 'icon-food.png', checkPlace: 'ԲԱՐ', cost: 530, income: '29.4', total: '530.0000', ingredients: [
      ['Հումք', 'Քանակ', 'Ընդհ. ինքնարժեք', 'Վերջին ձեռքբերման ինքնարժեք', 'Պահեստ'],
      ['Շոկոլադ', '0.060 Կիլոգրամ', '390.000', '390.000', 'Զաքյան'],
      ['Կոճապղպեղ', '0.020 Կիլոգրամ', '140.000', '140.000', 'Զաքյան'],
    ] },
    { id: 178, groupId: 11, name: 'Սառը շոկոլադ', price: 2500, image: 'icon-food.png', checkPlace: 'ԲԱՐ', cost: 735, income: '29.4', total: '735.0000', ingredients: [
      ['Հումք', 'Քանակ', 'Ընդհ. ինքնարժեք', 'Վերջին ձեռքբերման ինքնարժեք', 'Պահեստ'],
      ['Շոկոլադ', '0.080 Կիլոգրամ', '520.000', '520.000', 'Զաքյան'],
      ['Սառույց', '0.200 Կիլոգրամ', '35.000', '35.000', 'Բար'],
      ['Կաթ', '0.250 Լիտր', '180.000', '180.000', 'Զաքյան'],
    ] },
    { id: 179, groupId: 11, name: 'Սինամոն շոկոլադ', price: 2600, image: 'icon-food.png', checkPlace: 'ԲԱՐ', cost: 770, income: '29.6', total: '770.0000', ingredients: [
      ['Հումք', 'Քանակ', 'Ընդհ. ինքնարժեք', 'Վերջին ձեռքբերման ինքնարժեք', 'Պահեստ'],
      ['Շոկոլադ', '0.080 Կիլոգրամ', '520.000', '520.000', 'Զաքյան'],
      ['Դարչին', '0.010 Կիլոգրամ', '70.000', '70.000', 'Զաքյան'],
      ['Կաթ', '0.250 Լիտր', '180.000', '180.000', 'Զաքյան'],
    ] },
    { id: 180, groupId: 11, name: 'Տաք շոկոլադ', price: 1100, image: 'icon-food.png', checkPlace: 'ԲԱՐ', cost: 330, income: '30', total: '330.0000', ingredients: [
      ['Հումք', 'Քանակ', 'Ընդհ. ինքնարժեք', 'Վերջին ձեռքբերման ինքնարժեք', 'Պահեստ'],
      ['Կաթ', '0.200 Լիտր', '145.000', '145.000', 'Զաքյան'],
      ['Կակաո', '0.025 Կիլոգրամ', '185.000', '185.000', 'Զաքյան'],
    ] },
  ];

  const groupTabsHtml = groupTabs.map((tab, index) => `<li class="${index === 5 ? 'active' : ''}">
    <a class="menu_groups" href="menu.html?id=${11 + index}">${tab}</a>
  </li>`).join('');

  const frozenBadge = `<span class="freezed_item">
      <span>
        <i class="fa fa-snowflake-o" aria-hidden="true"></i>
        <span>Ապրանքը սառեցված է</span>
      </span>
    </span>`;

  const menuItemArticles = menuItems.map(item => `<article class="media item_view sortable_children display${item.freezed ? ' freezedItem' : ''}" id="p${item.id}" data-menu-item="${item.id}" data-group="${item.groupId}">
    ${item.freezed ? frozenBadge : ''}
    <a class="pull-left thumb p-thumb-2 imgContainer">
      <img src="assets/img/common/${item.image}" class="${item.image === 'icon-food.png' ? 'defImage' : ''}" alt="">
    </a>
    <div class="media-body">
      <a class="p-head">${item.name}</a>
      <p>${item.price} dram</p>
    </div>
  </article>`).join('');

  const menuItemData = JSON.stringify(menuItems).replace(/</g, '\\u003c');

  const menuPlaceFields = (values = {}) => `<div class="form-group">
      <label class="control-label col-sm-3">Անվանում:</label>
      <div class="col-sm-9"><input type="text" required class="form-control data" name="title" value="${values.title || ''}"></div>
    </div>
    <div class="form-group">
      <label class="control-label col-sm-3">Անվանում անգլերեն:</label>
      <div class="col-sm-9"><input type="text" class="form-control data" name="title_en" value="${values.en || ''}"></div>
    </div>
    <div class="form-group">
      <label class="control-label col-sm-3">Անվանում ռուսերեն:</label>
      <div class="col-sm-9"><input type="text" class="form-control data" name="title_ru" value="${values.ru || ''}"></div>
    </div>
    <div class="form-group">
      <label class="col-lg-3 control-label">Ցույց տալ պատվերի մեջ</label>
      <div class="col-lg-9"><div class="switch switch-square" data-on-label="Այո" data-off-label="Ոչ"><input type="checkbox" class="data switcher-item" name="show_in_order" checked></div></div>
    </div>
    <div class="form-group">
      <label class="control-label col-sm-3">Ցույց տալ Mootq-ում:</label>
      <div class="col-lg-3 control-label"><input type="checkbox" class="form-check-input data" name="public"></div>
    </div>
    <div class="form-group">
      <label class="control-label col-sm-3">Պահեստ:</label>
      <div class="col-sm-9"><select class="form-control data" name="store_id"><option>-- Պահեստ --</option>${places.map(place => `<option${place.store === values.store ? ' selected' : ''}>${place.store}</option>`).join('')}</select></div>
    </div>
    <div class="form-group">
      <label class="control-label col-sm-3">Հերթականություն:</label>
      <div class="col-sm-9"><input type="number" class="form-control data" name="sort_order" value="${values.order || ''}"></div>
    </div>
    <div class="form-group">
      <label class="control-label col-sm-3">Պատկեր:</label>
      <div class="col-sm-9 menu-icon-options">
        <label class="radio-inline"><i class="icon-food"></i><input type="radio" name="icon" value="icon-food" checked></label>
        <label class="radio-inline"><i class="icon-glass"></i><input type="radio" name="icon" value="icon-glass"></label>
        <label class="radio-inline"><i class="icon-beer"></i><input type="radio" name="icon" value="icon-beer"></label>
        <label class="radio-inline"><i class="icon-coffee"></i><input type="radio" name="icon" value="icon-coffee"></label>
        <label class="radio-inline"><i class="icon-truck"></i><input type="radio" name="icon" value="icon-truck"></label>
      </div>
    </div>
    <div class="form-group">
      <label class="control-label col-sm-3">Գույն:</label>
      <div class="col-sm-9"><input type="color" class="form-control data" name="color" value="${values.color || '#ec7b6a'}"></div>
    </div>
    <input type="hidden" class="data" name="hotel_item" value="0">`;

  const groupFields = (values = {}) => `<div class="form-group">
      <label class="control-label col-sm-3">Անվանում:</label>
      <div class="col-sm-9"><input type="text" required class="form-control data" name="title" value="${values.title || ''}"></div>
    </div>
    <div class="form-group">
      <label class="control-label col-sm-3">Անվանում անգլերեն:</label>
      <div class="col-sm-9"><input type="text" class="form-control data" name="title_en" value="${values.en || ''}"></div>
    </div>
    <div class="form-group">
      <label class="control-label col-sm-3">Անվանում ռուսերեն:</label>
      <div class="col-sm-9"><input type="text" class="form-control data" name="title_ru" value="${values.ru || ''}"></div>
    </div>
    <div class="form-group">
      <label class="control-label col-sm-3">Ցույց տալ Mootq-ում:</label>
      <div class="col-lg-3 control-label"><input type="checkbox" class="form-check-input data" name="public"></div>
    </div>
    <input hidden type="text" class="data" id="place_id" value="1" name="place">
    <input type="hidden" class="data" name="hotel_item" value="0">`;

  return `<div class="menu-page" data-menu-index-page>
    <div class="row menu_btn_radius">
      <div class="col-xs-12 clearfix menu-top-actions">
        <button class="btn btn-success m-bot15 pull-left" data-toggle="modal" id="openAddModalButton" data-target="#menuAddModal">
          <i class="icon-plus"></i> Ավելացնել Մենյու
        </button>
        <div class="col-xs-4 menu-search-wrap">
          <input type="text" placeholder="Search" id="search_in_group" class="form-control">
        </div>
      </div>
      <div class="sortableMenuPlace">${placeCards}</div>
    </div>
    <div id="excell"></div>

    <div id="menuAddModal" class="modal fade form-horizontal addModal" role="dialog">
      <div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Ավելացնել</h4></div><div class="modal-body"><form id="addMenuPlaceForm">${menuPlaceFields()}</form></div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-success pull-right" type="button">Հաստատել</button></div></div></div>
    </div>
    <div id="menuEditModal" class="modal fade form-horizontal editModal" role="dialog">
      <div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Փոփոխել</h4></div><div class="modal-body">${menuPlaceFields({ title: 'Խոհանոց', en: 'Kitchen', ru: 'Кухня', store: 'Զաքյան', order: '1', color: '#ec7b6a' })}</div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-warning pull-right" type="button">Հաստատել</button></div></div></div>
    </div>
    <div id="menuDeleteModal" class="modal fade deleteModal" role="dialog">
      <div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h4 class="modal-title">Ջնջել մենյուն</h4></div><div class="modal-body"><p>Ջնջե՞լ նշված մենյուն</p></div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-danger pull-right" type="button">Հաստատել</button></div></div></div>
    </div>
    <div id="menuAddGroupModal" class="modal fade form-horizontal addModal" role="dialog">
      <div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Ավելացնել բաժին</h4></div><div class="modal-body">${groupFields()}</div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-success pull-right" type="button">Հաստատել</button></div></div></div>
    </div>
    <div id="menuEditGroupModal" class="modal fade form-horizontal editModal" role="dialog">
      <div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Փոփոխել բաժինը</h4></div><div class="modal-body">${groupFields({ title: 'Աղցաններ', en: 'Salads', ru: 'Салаты' })}</div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-warning pull-right" type="button">Հաստատել</button></div></div></div>
    </div>
    <div id="menuDeleteGroupModal" class="modal fade deleteModal" role="dialog">
      <div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h4 class="modal-title">Ջնջել բաժինը</h4></div><div class="modal-body"><p>Ջնջե՞լ նշված բաժինը</p></div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-danger pull-right" type="button">Հաստատել</button></div></div></div>
    </div>
  </div>

  <div class="menu-group-page" data-menu-group-page style="display:none">
    <script type="application/json" id="menuGroupPageData">${menuItemData}</script>
    <div class="row">
      <div class="col-xs-12 clearfix assort_by">
        <button class="btn btn-success m-bot15 pull-left" data-toggle="modal" data-target="#menuGroupAddItemModal">
          <i class="icon-plus"></i> Ավելացնել Ապրանք
        </button>
        <div class="input-group m-bot15 col-xs-4 menu-group-search">
          <input type="text" class="form-control" name="command" placeholder="Փնտրել" id="command">
        </div>
        <form action="" method="post" class="form-inline assort_form">
          <span>Դասավորել ըստ՝ </span>
          <button type="button" class="btn btn-warning" disabled>Օգտատերի</button>
          <button type="button" class="btn btn-warning">Անվան</button>
          <button type="button" class="btn btn-warning">Արժեքի</button>
        </form>
      </div>
      <div class="group_main_content">
        <div>
          <section class="panel" id="group_main_menu">
            <header class="panel-heading">
              <ul class="nav nav-tabs">${groupTabsHtml}</ul>
            </header>
            <div id="add_product" style="display:none;position:absolute"></div>
            <div class="panel-body sortable articles" data-table="menu_items">${menuItemArticles}</div>
          </section>
        </div>
        <div class="group_right_column">
          <div>
            <section class="panel" id="item_content">
              <div class="twt-feed">
                <div>
                  <h1 class="name" data-menu-detail="name">Կինդեր կակաո</h1>
                  <div class="groupButtonsLine">
                    <button type="button" class="btn btn-warning btn-xs btn_edit inTableIconButton" data-toggle="modal" data-target="#editItem">
                      <img src="assets/img/icons/pencil.svg" alt="">
                    </button>
                    <button type="button" class="btn btn-danger btn-xs btn_delete inTableIconButton" data-toggle="modal" data-target="#menuGroupDeleteItemModal">
                      <img src="assets/img/icons/trash.svg" alt="">
                    </button>
                    <button type="button" class="btn btn-success btn-xs open-comment-modal inTableIconButton" data-toggle="modal" data-target="#commentModal" data-item="173"><i class="icon-comment"></i></button>
                    <button type="button" class="btn btn-default btn-xs open-happy-modal inTableIconButton" data-toggle="modal" data-target="#happyModal" data-item="173"><i class="icon-time"></i></button>
                    <button class="btn btn-default btn-xs excel-download inTableIconButton"><i class="icon-download"></i></button>
                  </div>
                </div>
                <a><img src="assets/img/common/icon-food.png" class="logo" data-menu-detail="image" alt=""></a>
              </div>
              <div class="weather-category twt-category">
                <ul>
                  <li><span>Գինը</span><h5 class="price" data-menu-detail="price">2800</h5></li>
                  <li><span>Պատրաստման վայր</span><h5 class="check_place" data-menu-detail="checkPlace">ԲԱՐ</h5></li>
                  <li><span>Ինքնարժեք</span><h5 class="cost_price" data-menu-detail="cost">832</h5></li>
                  <li><span>Ինքնարժեքի տոկոս</span><h5 class="income" data-menu-detail="income">29.7%</h5></li>
                </ul>
              </div>
            </section>
            <section class="panel item_content_form checkPackage">
              <div class="panel-body form-inline">
                <div class="row stand">
                  <div class="form-group col-xs-12">
                    <select class="form-control packages"><option value="0">--Ստանդարտ--</option><option>Lunch set</option></select>
                  </div>
                </div>
                <div class="add_raw">
                  <div class="raw_content">
                    <div class="form-group product_select">
                      <select class="form-control"><option>--Հումք --</option><option>Կլար քառ Լիտր</option><option>Կինդեր Հատ</option><option>Տաք շոկոլադ Կիլոգրամ</option></select>
                    </div>
                    <div class="unit">
                      <div class="form-group"><input type="number" class="form-control" name="count" placeholder="քանակ"></div>
                      <h5 style="display:inline-block;"><strong>/<span id="count-span">Լիտր</span></strong></h5>
                    </div>
                    <div class="form-group place_select">
                      <select class="form-control"><option>Զաքյան</option><option>Rosemary</option><option>Բար</option></select>
                    </div>
                  </div>
                  <div class="add_ingredients">
                    <button type="button" class="btn btn-success addContent"><i class="icon-plus"></i> Ավելացնել բաղադրություն</button>
                    <div><a href="#copyModalFirst" class="copy-content-to-clipboard" data-toggle="modal" data-target="#copyModalFirst"><i class="fa fa-file" aria-hidden="true"></i> Կրկ․</a><a href="#copyModal" class="paste-from-clipboard" data-toggle="modal" data-target="#copyModal"><i class="fa fa-clipboard" aria-hidden="true"></i> Տեղ․</a></div>
                  </div>
                </div>
              </div>
            </section>
            <div class="scroll-table clearfix">
              <table class="table table-bordered content_table">
                <thead data-menu-ingredients-head></thead>
                <tbody data-menu-ingredients></tbody>
              </table>
            </div>
            <button type="button" class="btn btn-success btn-prepare btn-block hidden"><i class="icon-plus"></i> Ավելացնել փաթեթ</button>
            <button type="button" class="btn btn-success btn-prepare btn-block addPackageGroupModal hide"><i class="icon-plus"></i> Ավելացնել փաթեթի խումբ</button>
          </div>
        </div>
      </div>
      <div class="text-center mtt_">
        <button class="btn btn-primary btn-xs"><a href="menu.html?id=21" style="color:#fff;font-size:15px;"><i class="icon icon-arrow-right"></i> Բար</a></button>
        <button class="btn btn-primary btn-xs"><a href="menu.html?id=31" style="color:#fff;font-size:15px;"><i class="icon icon-arrow-right"></i> Հացաբուլկեղեն</a></button>
      </div>
    </div>

    <div id="menuGroupAddItemModal" class="modal fade form-horizontal addModal" role="dialog">
      <div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Ավելացնել</h4></div><div class="modal-body">
        <div class="form-group"><label class="control-label col-sm-4">Անվանում:</label><div class="col-sm-8"><input class="form-control data" name="name"></div></div>
        <div class="form-group"><label class="control-label col-sm-4">Արժեք:</label><div class="col-sm-8"><input type="number" class="form-control data" name="price"></div></div>
        <div class="form-group"><label class="control-label col-sm-4">Պատրաստման վայր:</label><div class="col-sm-8"><select class="form-control data"><option>Խոհանոց</option><option>Բար</option></select></div></div>
        <div class="form-group"><label class="control-label col-sm-4">Նկարագրություն:</label><div class="col-sm-8"><textarea class="form-control data"></textarea></div></div>
      </div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-success pull-right" type="button">Հաստատել</button></div></div></div>
    </div>
    <div id="editItem" class="modal fade form-horizontal editModal" role="dialog">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <h4 class="modal-title">Փոփոխել</h4>
          </div>
          <div class="modal-body">
            <div class="form-group"><label class="control-label col-sm-4">Տիպ:</label><div class="col-sm-8"><select class="form-control data" name="type" disabled><option selected>Ապրանք</option><option>Ծառայություն</option><option>Փաթեթ</option></select></div></div>
            <div class="form-group"><label class="control-label col-sm-4">Անվանում:</label><div class="col-sm-8"><input type="text" required class="form-control data validateInput" name="name" data-menu-edit-name value="Կինդեր կակաո"></div></div>
            <div class="form-group"><label class="control-label col-sm-4">Անվանում Անգլերեն:</label><div class="col-sm-8"><input type="text" class="form-control data validateInput" name="name_en" value="Kinder cocoa"></div></div>
            <div class="form-group"><label class="control-label col-sm-4">Անվանում Ռուսերեն:</label><div class="col-sm-8"><input type="text" class="form-control data validateInput" name="name_ru" value="Киндер какао"></div></div>
            <div class="form-group"><label class="control-label col-sm-4">Հդմ անդորագրի համար:</label><div class="col-sm-8"><input type="text" class="form-control data" name="hdm_name" value="Կինդեր կակաո"></div></div>
            <div class="form-group"><label class="control-label col-sm-4">Արժեք:</label><div class="col-sm-8"><input type="number" required class="form-control data non-negative-input" name="price" data-menu-edit-price value="2800"></div></div>
            <div class="form-group hide"><label class="control-label col-sm-4">Cashback:</label><div class="col-sm-8"><input type="number" value="0" max="100" class="form-control data" name="cashback"></div></div>
            <div class="form-group"><label class="control-label col-sm-4">Պատրաստման վայր:</label><div class="col-sm-8"><select name="check_place" required class="form-control data"><option value="">--Պատրաստման վայր--</option><option selected>ԲԱՐ</option><option>Խոհանոց</option></select></div></div>
            <div class="form-group"><label class="control-label col-sm-4">ՀԴՄ բաժին:</label><div class="col-sm-8"><input type="number" value="1" class="form-control data" name="hdm_dep"></div></div>
            <div class="form-group"><label class="control-label col-sm-4">Բաժին:</label><div class="col-sm-8"><select name="group" required class="form-control data"><option value="">--Բաժին--</option><option selected>Շոկոլադե ըմպելիքներ</option><option>Գինի Բաժակով</option><option>Տաք և Սառը ըմպելիքներ</option></select></div></div>
            <div class="form-group"><label class="control-label col-sm-4">Նկարագրություն:</label><div class="col-sm-8"><textarea class="form-control data" name="description"></textarea></div></div>
            <div class="form-group default-checked-trigger"><div class="m-bot15"><label class="col-lg-4 control-label">Տոկոսավճարի ազդեցություն</label><div class="col-lg-8"><div class="switch switch-square" data-on-label="Այո" data-off-label="Ոչ"><input type="checkbox" class="data switcher-item" name="comissions_access" value="1"></div></div></div></div>
            <div class="form-group"><label class="control-label col-sm-4">Քարտի տոկոս:</label><div class="col-sm-8"><input type="number" max="99" min="0" class="form-control data non-negative-input" name="extra_percent_for_bonus_card"></div></div>
            <div class="form-group"><label class="control-label col-sm-4">Պատրաստման միջին ժամանակ:</label><div class="col-sm-8"><div class="input-group"><input type="number" min="0" class="form-control data non-negative-input" name="default_cook_interval" value="0"><label class="input-group-addon">րոպե</label></div></div></div>
            <div class="form-group"><div class="m-bot15"><label class="col-lg-4 control-label">Սառեցնել</label><div class="col-lg-8"><div class="switch switch-square" data-on-label="Այո" data-off-label="Ոչ"><input type="checkbox" class="data switcher-item" name="freezed" value="1"></div></div></div></div>
            <div class="form-group"><label class="control-label col-sm-4">Բառկոդ:</label><div class="col-sm-8"><input type="text" class="form-control data" name="barcode"></div></div>
            <div class="form-group"><label class="control-label col-sm-4">ԱԴԳ կոդ:</label><div class="col-sm-8"><input type="text" class="form-control data" name="adg" value="56.10"></div></div>
            <div class="form-group"><label class="control-label col-sm-4">կոդ:</label><div class="col-sm-8"><input type="text" class="form-control data" name="code"></div></div>
            <div class="form-group"><label class="control-label col-sm-4">Նկար</label><div class="col-sm-8 image_upload_container"><div class="upload">Ընտրել նկար</div><input type="hidden" class="data photo_uploaded" name="logo" value="avatar.png"><span>Max 10 MB</span></div></div>
            <input type="hidden" class="data" name="hotel_item" value="0">
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button>
            <button class="btn btn-warning pull-right editSubmit" type="button" data-table="menu_items">Հաստատել</button>
          </div>
        </div>
      </div>
    </div>
    <div id="menuGroupDeleteItemModal" class="modal fade deleteModal" role="dialog">
      <div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h4 class="modal-title">Ջնջել մենյուն</h4></div><div class="modal-body"><p>Ջնջե՞լ նշված տեսականին</p></div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-danger pull-right" type="button">Հաստատել</button></div></div></div>
    </div>
    <div id="menuContentDeleteModal" class="modal fade deleteModal" role="dialog">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <h4 class="modal-title">Ջնջել</h4>
          </div>
          <div class="modal-body">
            <p>Ջնջե՞լ նշված բաղադրությունը</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button>
            <button class="btn btn-danger pull-right" type="button" id="confirmDeleteMenuContent" data-dismiss="modal">Հաստատել</button>
          </div>
        </div>
      </div>
    </div>
    <div id="commentModal" class="modal fade" role="dialog">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <h4 class="modal-title">Նկարագրության նմուշներ</h4>
          </div>
          <div class="modal-body clearfix">
            <div class="add_modal_Design">
              <div class="form-group clearfix">
                <div class="col-sm-12">
                  <div>
                    <label class="control-label">Անվանում:</label>
                    <input type="text" required id="template" name="template" class="form-control">
                  </div>
                  <input type="hidden" id="item_id" value="173">
                </div>
              </div>
              <div class="form-group clearfix">
                <button class="btn btn-success" id="addTemplate">Ավելացնել</button>
              </div>
            </div>
            <div class="row modal_Table">
              <table class="table table-bordered">
                <thead>
                  <tr>
                    <th>Անվանում</th>
                    <th><i class="icon-trash"></i></th>
                  </tr>
                </thead>
                <tbody id="templates">
                  <tr data-id="1"><td>Առանց շաքարի</td><td><button class="btn btn-xs btn-danger deleteTemplate"><i class="icon-trash"></i></button></td></tr>
                  <tr data-id="2"><td>Ավելացնել դարչին</td><td><button class="btn btn-xs btn-danger deleteTemplate"><i class="icon-trash"></i></button></td></tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button>
          </div>
        </div>
      </div>
    </div>
    <div id="happyModal" class="modal fade" role="dialog">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <h4 class="modal-title">Happy hour</h4>
          </div>
          <div class="modal-body clearfix">
            <div class="form-group clearfix">
              <div class="col-md-6">
                <label class="control-label">Արժեք:</label>
                <input type="text" required class="happy-price non-negative-input form-control" name="happy-price">
              </div>
              <div class="form-group col-md-6 happy_date_margin">
                <label class="col-lg-12 control-label">Happy hour</label>
                <div class="input-group input-large padding-left0 happy_our_date">
                  <div class="input-group bootstrap-timepicker">
                    <span class="input-group-btn">
                      <button class="btn btn-default button-time-icon" type="button"><i class="icon-time"></i></button>
                    </span>
                    <input type="text" readonly class="form-control timepicker-24 tp1 border-radius0">
                  </div>
                  <span class="input-group-addon border-radius0 happy_date_span">ից</span>
                  <div class="input-group bootstrap-timepicker">
                    <input type="text" readonly class="form-control timepicker-24 tp2 border-radius0">
                    <span class="input-group-btn">
                      <button class="btn btn-default button-time-icon" type="button"><i class="icon-time"></i></button>
                    </span>
                  </div>
                </div>
                <input type="hidden" id="happy_item" value="173">
              </div>
            </div>
            <div class="form-group col-lg-12 happy_days">
              <select class="form-control select2" multiple id="happy_days">
                <option value="1">Երկուշաբթի</option>
                <option value="2">Երեքշաբթի</option>
                <option value="3">Չորեքշաբթի</option>
                <option value="4">Հինգշաբթի</option>
                <option value="5">Ուրբաթ</option>
                <option value="6">Շաբաթ</option>
                <option value="0">Կիրակի</option>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button>
            <button type="button" class="btn btn-warning pull-left cancel-happy-hour">Հեռացնել</button>
            <button type="button" class="btn btn-success pull-right" id="saveHappyHours">Հաստատել</button>
          </div>
        </div>
      </div>
    </div>
    <div aria-hidden="true" aria-labelledby="copyModalLabel" role="dialog" tabindex="-1" id="copyModal" class="modal fade groupModal copy-alert-modal">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <div class="copy-alert-icon">!</div>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <h4 class="modal-title">Տեղադրե՞լ</h4>
          </div>
          <div class="modal-body">
            <p class="copy-popup-message">Տեղադրե՞լ պատճենված բաղադրությունը</p>
          </div>
          <div class="modal-footer">
            <button id="copyContentToClipboard" class="btn btn-success" name="copyContent" data-dismiss="modal">Հաստատել</button>
          </div>
        </div>
      </div>
    </div>
    <div aria-hidden="true" aria-labelledby="copyModalFirstLabel" role="dialog" tabindex="-1" id="copyModalFirst" class="modal fade groupModal copy-alert-modal">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <div class="copy-alert-icon">!</div>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <h4 class="modal-title">Պատճենե՞լ</h4>
          </div>
          <div class="modal-body">
            <p class="copy-popup-message">Ապրանքի բովանդակությունը հաջողությամբ պատճենվեց</p>
          </div>
          <div class="modal-footer">
            <button id="copyContentToClipboardTop" class="btn btn-success" name="copyContent" data-dismiss="modal">Հաստատել</button>
          </div>
        </div>
      </div>
    </div>

    <script>
      (function () {
        var params = new URLSearchParams(window.location.search);
        var hasGroupRoute = params.has('id') || params.has('item');
        var indexPage = document.querySelector('[data-menu-index-page]');
        var groupPage = document.querySelector('[data-menu-group-page]');
        if (!hasGroupRoute || !groupPage) return;
        if (indexPage) indexPage.style.display = 'none';
        groupPage.style.display = '';

        var dataEl = document.getElementById('menuGroupPageData');
        var items = dataEl ? JSON.parse(dataEl.textContent || '[]') : [];
        var requestedItem = Number(params.get('item'));
        var requestedGroup = Number(params.get('id'));
        var current = items.find(function (item) {
          return item.id === requestedItem || (item.aliases || []).indexOf(requestedItem) !== -1;
        }) ||
          items.find(function (item) { return item.groupId === requestedGroup; }) ||
          items[0];
        if (!current) return;

        var escapeHtml = function (value) {
          return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[ch];
          });
        };
        var percent = current.income || (current.price ? Math.round(current.cost * 100 / current.price) : 0);
        groupPage.querySelector('[data-menu-detail="name"]').textContent = current.name;
        groupPage.querySelector('[data-menu-detail="price"]').textContent = current.price;
        groupPage.querySelector('[data-menu-detail="checkPlace"]').textContent = current.checkPlace;
        groupPage.querySelector('[data-menu-detail="cost"]').textContent = current.cost;
        groupPage.querySelector('[data-menu-detail="income"]').textContent = percent + '%';
        groupPage.querySelector('[data-menu-detail="image"]').setAttribute('src', 'assets/img/common/' + current.image);
        groupPage.querySelector('[data-menu-edit-name]').value = current.name;
        groupPage.querySelector('[data-menu-edit-price]').value = current.price;
        groupPage.querySelector('[data-target="#happyModal"]').setAttribute('data-item', current.id);
        document.getElementById('happy_item').value = current.id;
        groupPage.querySelectorAll('[data-menu-item]').forEach(function (article) {
          article.classList.toggle('added', Number(article.getAttribute('data-menu-item')) === current.id);
          article.addEventListener('click', function () {
            var itemId = Number(article.getAttribute('data-menu-item'));
            var url = new URL(window.location.href);
            url.searchParams.set('item', itemId);
            window.location.href = url.href;
          });
        });
        var head = current.ingredients[0] || [];
        var rows = current.ingredients.slice(1);
        groupPage.querySelector('[data-menu-ingredients-head]').innerHTML = '<tr>' +
          head.map(function (cell) { return '<th>' + escapeHtml(cell) + '</th>'; }).join('') +
          '<th><i class="icon-eye-open"></i></th><th><i class="icon-trash"></i></th><th><i class="icon-pencil"></i></th></tr>';
        var body = groupPage.querySelector('[data-menu-ingredients]');
        body.innerHTML = rows.map(function (row, rowIndex) {
          var contentId = current.id + '-' + rowIndex;
          return '<tr data-content-row="' + contentId + '">' +
            row.map(function (cell, index) {
              if (index === 0) {
                return '<td><a href="item.html?id=173">' + escapeHtml(cell) + '</a></td>';
              }
              if (index === 1) {
                var parts = String(cell).split(' ');
                return '<td data-col="count" data-count="' + escapeHtml(parts[0]) + '" data-value="' + escapeHtml(parts.slice(1).join(' ')) + '">' + escapeHtml(cell) + '</td>';
              }
              return '<td>' + escapeHtml(cell) + '</td>';
            }).join('') +
            '<td><button type="button" class="btn btn-success btn-xs btn_visibility inTableIconButton" value="' + contentId + '" data-vis="1"><i class="icon-eye-open"></i></button></td>' +
            '<td><button type="button" class="btn btn-danger btn-xs btn_delete inTableIconButton" value="' + contentId + '" data-toggle="modal" data-target="#menuContentDeleteModal"><img src="assets/img/icons/trash.svg" alt=""></button></td>' +
            '<td><button type="button" class="btn btn-warning btn-xs btn-edit-menu-item-content inTableIconButton" data-menu-item-id="' + current.id + '" data-content-id="' + contentId + '"><img src="assets/img/icons/pencil.svg" alt=""></button><button type="button" style="display:none;" class="btn btn-success btn-xs btn-submit-menu-item-content inTableIconButton"><i class="icon-ok"></i></button></td>' +
          '</tr>';
        }).join('') + '<tr class="menu-total-row"><td colspan="8">' + escapeHtml(current.total || current.cost) + '</td></tr>';

        var pendingDeleteRow = null;
        body.querySelectorAll('.btn_visibility').forEach(function (button) {
          button.addEventListener('click', function () {
            var visible = button.getAttribute('data-vis') === '1';
            button.setAttribute('data-vis', visible ? '0' : '1');
            button.classList.toggle('btn-success', !visible);
            button.classList.toggle('btn-warning', visible);
            var icon = button.querySelector('i');
            icon.classList.toggle('icon-eye-open', !visible);
            icon.classList.toggle('icon-eye-close', visible);
          });
        });
        body.querySelectorAll('.btn_delete').forEach(function (button) {
          button.addEventListener('click', function () {
            pendingDeleteRow = button.closest('tr');
          });
        });
        var confirmDelete = document.getElementById('confirmDeleteMenuContent');
        if (confirmDelete) {
          confirmDelete.onclick = function () {
            if (pendingDeleteRow) pendingDeleteRow.remove();
            pendingDeleteRow = null;
          };
        }
        body.querySelectorAll('.btn-edit-menu-item-content').forEach(function (button) {
          button.addEventListener('click', function () {
            var row = button.closest('tr');
            var editable = row.querySelector('td[data-col="count"]');
            if (!editable || row.querySelector('.input-edit-menu-item-content')) return;
            var oldCount = parseFloat(editable.getAttribute('data-count')) || 0;
            var value = editable.getAttribute('data-value') || '';
            editable.innerHTML = '<input class="w-100 form-control input-edit-menu-item-content editValid" type="number" min="0" value="' + oldCount + '">';
            var input = editable.querySelector('input');
            var submit = row.querySelector('.btn-submit-menu-item-content');
            button.style.display = 'none';
            submit.style.display = '';
            input.focus();
            input.select();
            input.addEventListener('keyup', function (event) {
              if (Number(input.value) < 0) input.value = 0;
              if (event.which === 13 || event.key === 'Enter') submit.click();
            });
            submit.onclick = function () {
              var newCount = parseFloat(input.value) || 0;
              editable.setAttribute('data-count', newCount);
              editable.textContent = newCount.toFixed(3) + (value ? ' ' + value : '');
              button.style.display = '';
              submit.style.display = 'none';
            };
          });
        });
      })();
    </script>
  </div>`;
}

function roomsHallContentReports() {
  return `<section class="panel"><div class="panel-body"><form class="form-inline"><button type="button" class="btn btn-info" data-toggle="modal" data-target="#addModal">Ավելացնել նոր հարկ</button> <button type="button" class="btn btn-default" style="background:#33C481;color:#fff" data-toggle="modal" data-target="#typeModal">Սեղանների տիպեր <i class="fa fa-table"></i></button> <button type="button" class="btn btn-warning" data-toggle="modal" data-target="#myModal-add">Ավելացնել նոր սրահ</button></form></div></section><div class="row report_main_content"><div class="reportType col-md-4 col-sm-12"><aside class="profile-nav alt green-border report-card"><section class="panel"><div class="user-heading alt green-bg reportTitle"><h1 class="text-center">Առաջին հարկ</h1></div><ul class="nav nav-pills nav-stacked reportsList"><li><a>Main Hall <span class="label label-info pull-right r-activity">8 սեղան</span></a></li><li><a>VIP սրահ <span class="label label-info pull-right r-activity">4 սեղան</span></a></li><li><a>Terrace <span class="label label-info pull-right r-activity">6 սեղան</span></a></li><li><a data-toggle="modal" data-target="#editModal">Փոփոխել հարկը <span class="label label-info pull-right r-activity"><i class="fa fa-pencil"></i></span></a></li><li><a data-toggle="modal" data-target="#deleteModal">Ջնջել հարկը <span class="label label-info pull-right r-activity"><i class="fa fa-trash"></i></span></a></li></ul></section></aside></div><div class="reportType col-md-4 col-sm-12"><aside class="profile-nav alt report-card"><section class="panel"><div class="user-heading alt reportTitle"><h1 class="text-center">Երկրորդ հարկ</h1></div><ul class="nav nav-pills nav-stacked reportsList"><li><a>Lounge <span class="label label-info pull-right r-activity">5 սեղան</span></a></li><li><a>Ծննդյան սրահ <span class="label label-info pull-right r-activity">7 սեղան</span></a></li><li><a data-toggle="modal" data-target="#myModal-edit">Փոփոխել սրահը <span class="label label-info pull-right r-activity"><i class="fa fa-pencil"></i></span></a></li><li><a data-toggle="modal" data-target="#myModal-del">Ջնջել սրահը <span class="label label-info pull-right r-activity"><i class="fa fa-trash"></i></span></a></li></ul></section></aside></div><div class="reportType col-md-4 col-sm-12"><aside class="profile-nav alt report-card"><section class="panel"><div class="user-heading alt reportTitle"><h1 class="text-center">Բացօթյա տարածք</h1></div><ul class="nav nav-pills nav-stacked reportsList"><li><a>Garden <span class="label label-info pull-right r-activity">10 սեղան</span></a></li><li><a>Ընդհանուր սրահներ <span class="label label-info pull-right r-activity">6</span></a></li><li><a>Ընդհանուր սեղաններ <span class="label label-info pull-right r-activity">40</span></a></li><li><a data-toggle="modal" data-target="#myModal-del2">Ակտիվ սեղաններով սրահ <span class="label label-info pull-right r-activity">blocked</span></a></li></ul></section></aside></div></div>
  <div id="myModal-add" class="modal fade" role="dialog" aria-hidden="true"><div class="modal-dialog modal_left_and_top_padding40 change_hall_modal"><div class="modal-content"><div class="modal-header"><button class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Ավելացնել նոր սրահ</h4></div><div class="modal-body"><form class="form-horizontal"><div class="form-group"><label class="col-lg-4 control-label">Սրահի անվանումը *</label><div class="col-lg-8"><input type="text" class="form-control" value="Նոր սրահ"></div></div><div class="form-group"><label class="col-lg-4 control-label">Պատրաստման վայր</label><div class="col-lg-8"><input type="text" class="form-control" value="Խոհանոց"></div></div><div class="form-group"><label class="control-label col-lg-4">Ընտրեք գույնը</label><div class="col-md-8"><input type="color" value="#5FA8D3" class="form-control"></div></div><button type="button" class="finish btn btn-success">Հաստատել</button></form></div></div></div></div>
  <div id="myModal-edit" class="modal fade" role="dialog" aria-hidden="true"><div class="modal-dialog modal_left_and_top_padding40 change_hall_modal"><div class="modal-content"><div class="modal-header"><button class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Փոփոխել սրահը</h4></div><div class="modal-body"><form class="form-horizontal"><div class="form-group"><label class="col-lg-4 control-label">Սրահի անվանումը *</label><div class="col-lg-8"><input type="text" class="form-control" value="Main Hall"></div></div><div class="form-group"><label class="col-lg-4 control-label">Պատրաստման վայր</label><div class="col-lg-8"><input type="text" class="form-control" value="Խոհանոց"></div></div><div class="form-group"><label class="control-label col-lg-4">Ընտրեք գույնը</label><div class="col-md-8"><input type="color" value="#78CD51" class="form-control"></div></div><button type="button" class="finish btn btn-success">Հաստատել</button></form></div></div></div></div>
  <div id="myModal-del" class="modal fade" role="dialog" aria-hidden="true"><div class="modal-dialog modal_left_and_top_padding40"><div class="modal-content"><div class="modal-header"><h4 class="modal-title">Ջնջել սրահը</h4></div><div class="modal-body text-center"><p>Դուք համոզված եք ջնջել սրահը</p><button type="button" class="finish btn btn-danger">Ջնջել</button></div><div class="modal-footer"><button class="btn btn-default pull-left" data-dismiss="modal">Փակել</button></div></div></div></div>
  <div id="myModal-del2" class="modal fade" role="dialog" aria-hidden="true"><div class="modal-dialog modal_left_and_top_padding40"><div class="modal-content"><div class="modal-header"><h4 class="modal-title">Ջնջել սրահը</h4></div><div class="modal-body text-center"><p style="font-size:19px">Դուք չեք կարող տվյալ պահին ջնջել այս սրահը, քանի որ նրա մեջ կան ակտիվ սեղաններ:</p><button type="button" class="finish btn btn-danger">Փակել ակտիվ սեղաները</button></div><div class="modal-footer"><button class="btn btn-default pull-left" data-dismiss="modal">Փակել</button></div></div></div></div>
  <div id="typeModal" class="modal fade" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Սեղանի տեսակներ</h4></div><div class="modal-body clearfix"><div class="form-group clearfix"><div class="col-sm-12"><div class="form-group col-md-4"><label class="control-label">Անվանում hy:</label><input type="text" class="form-control" value="Սեղան"></div><div class="form-group col-md-4"><label class="control-label">Անվանում en:</label><input type="text" class="form-control" value="Table"></div><div class="form-group col-md-4"><label class="control-label">Անվանում ru:</label><input type="text" class="form-control" value="Стол"></div></div></div><button class="btn btn-success">Ավելացնել</button><table class="table table-bordered"><tbody><tr><td><input class="form-control" disabled value="Սեղան"></td><td><input class="form-control" disabled value="Table"></td><td><input class="form-control" disabled value="Стол"></td></tr><tr><td><input class="form-control" disabled value="VIP"></td><td><input class="form-control" disabled value="VIP"></td><td><input class="form-control" disabled value="VIP"></td></tr></tbody></table></div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button></div></div></div></div>`;
}

function roomsHallContent() {
  const floors = [
    ['Առաջին հարկ', ['Main Hall', 'VIP սրահ', 'Terrace']],
    ['Երկրորդ հարկ', ['Lounge', 'Ծննդյան սրահ']],
    ['Բացօթյա տարածք', ['Garden']],
  ];
  const floorCards = floors.map(([floor, halls], floorIndex) => `<div class="col-sm-6 col-xs-12 floreContainer">
    <aside class="profile-nav alt">
      <section class="panel">
        <div class="user-heading alt StoreContainerHeader">
          <h1 class="floreName text-center">${floor}</h1>
          <p class="text-center menu_it buttonsInFloreContainer">
            <a href="rooms-hall-planning.html" class="btn btn-primary btn-xs planningButton">Հատակագիծ</a>
            <button type="button" class="btn btn-warning btn-xs get-edit-floor changeButton" data-toggle="modal" data-target="#editModal"><img src="assets/img/icons/pencil.svg" alt=""></button>
            <button type="button" class="btn btn-danger btn-xs btn_delete deleteButton" data-toggle="modal" data-target="#deleteModal"><img src="assets/img/icons/trash.svg" alt=""></button>
          </p>
          <a href="#myModal-add" data-toggle="modal" class="btn btn-success addHallButton"><img src="assets/img/icons/plusIcon.svg" alt=""> Ավելացնել նոր սրահ</a>
        </div>
        <ul class="nav nav-pills nav-stacked sortable hallList" data-table="menu_group">
          ${halls.map((hall, hallIndex) => `<li class="hallContainer" data-item="${floorIndex + 1}${hallIndex + 1}">
            <a class="col-xs-7 hallName" href="rooms-hall-tables.html">${hall}</a>
            <div class="text-center col-xs-5 p-t-10 hallButtons">
              <a href="#myModal-edit" data-toggle="modal" class="place_edit btn btn-xs btn-warning changeButton"><img src="assets/img/icons/pencil.svg" alt=""></a>
              <a href="#${hallIndex === 1 && floorIndex === 0 ? 'myModal-del2' : 'myModal-del'}" data-toggle="modal" class="place_del btn btn-xs btn-danger deleteButton"><img src="assets/img/icons/trash.svg" alt=""></a>
            </div>
          </li>`).join('')}
        </ul>
      </section>
    </aside>
  </div>`).join('');

  return `<h2 class="pageTitle">Սրահներ/Սեղաններ-Կարգավորումներ</h2>
  <div class="row">
    <div class="col-xs-12 m-bot10 headerButtons">
      <a href="#addModal" data-toggle="modal" class="btn btn-success addNewFlore"><img src="assets/img/icons/plusIcon.svg" alt=""> Ավելացնել նոր հարկ</a>
      <button class="btn btn-primary tableTypes" data-toggle="modal" data-target="#typeModal">Սեղանների տիպեր</button>
    </div>
    <div class="col-xs-12 nopadding floresList">${floorCards}</div>
  </div>
  <div id="myModal-add" class="modal fade" role="dialog" aria-hidden="true"><div class="modal-dialog modal_left_and_top_padding40 change_hall_modal"><div class="modal-content"><div class="modal-header"><button class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Ավելացնել նոր սրահ</h4></div><div class="modal-body"><form class="form-horizontal"><div class="form-group"><label class="col-lg-4 control-label">Սրահի անվանումը *</label><div class="col-lg-8"><input type="text" class="form-control" value="Նոր սրահ"></div></div><div class="form-group"><label class="col-lg-4 control-label">Պատրաստման վայր</label><div class="col-lg-8"><input type="text" class="form-control" value="Խոհանոց"></div></div><div class="form-group"><label class="control-label col-lg-4">Ընտրեք գույնը</label><div class="col-md-8"><input type="color" value="#5FA8D3" class="form-control"></div></div><button type="button" class="finish btn btn-success">Հաստատել</button></form></div></div></div></div>
  <div id="myModal-edit" class="modal fade" role="dialog" aria-hidden="true"><div class="modal-dialog modal_left_and_top_padding40 change_hall_modal"><div class="modal-content"><div class="modal-header"><button class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Փոփոխել սրահը</h4></div><div class="modal-body"><form class="form-horizontal"><div class="form-group"><label class="col-lg-4 control-label">Սրահի անվանումը *</label><div class="col-lg-8"><input type="text" class="form-control" value="Main Hall"></div></div><div class="form-group"><label class="col-lg-4 control-label">Պատրաստման վայր</label><div class="col-lg-8"><input type="text" class="form-control" value="Խոհանոց"></div></div><div class="form-group"><label class="control-label col-lg-4">Ընտրեք գույնը</label><div class="col-md-8"><input type="color" value="#78CD51" class="form-control"></div></div><button type="button" class="finish btn btn-success">Հաստատել</button></form></div></div></div></div>
  <div id="myModal-del" class="modal fade" role="dialog" aria-hidden="true"><div class="modal-dialog modal_left_and_top_padding40"><div class="modal-content"><div class="modal-header"><h4 class="modal-title">Ջնջել սրահը</h4></div><div class="modal-body text-center"><p>Դուք համոզված եք ջնջել սրահը</p><button type="button" class="finish btn btn-danger">Ջնջել</button></div><div class="modal-footer"><button class="btn btn-default pull-left" data-dismiss="modal">Փակել</button></div></div></div></div>
  <div id="myModal-del2" class="modal fade" role="dialog" aria-hidden="true"><div class="modal-dialog modal_left_and_top_padding40"><div class="modal-content"><div class="modal-header"><h4 class="modal-title">Ջնջել սրահը</h4></div><div class="modal-body text-center"><p style="font-size:19px">Դուք չեք կարող տվյալ պահին ջնջել այս սրահը, քանի որ նրա մեջ կան ակտիվ սեղաններ:</p><button type="button" class="finish btn btn-danger">Փակել ակտիվ սեղաները</button></div><div class="modal-footer"><button class="btn btn-default pull-left" data-dismiss="modal">Փակել</button></div></div></div></div>
  <div id="typeModal" class="modal fade" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Սեղանի տեսակներ</h4></div><div class="modal-body clearfix"><div class="form-group clearfix"><div class="col-sm-12"><div class="form-group col-md-4"><label class="control-label">Անվանում hy:</label><input type="text" class="form-control" value="Սեղան"></div><div class="form-group col-md-4"><label class="control-label">Անվանում en:</label><input type="text" class="form-control" value="Table"></div><div class="form-group col-md-4"><label class="control-label">Անվանում ru:</label><input type="text" class="form-control" value="Стол"></div></div></div><button class="btn btn-success">Ավելացնել</button><table class="table table-bordered"><tbody><tr><td><input class="form-control" disabled value="Սեղան"></td><td><input class="form-control" disabled value="Table"></td><td><input class="form-control" disabled value="Стол"></td></tr><tr><td><input class="form-control" disabled value="VIP"></td><td><input class="form-control" disabled value="VIP"></td><td><input class="form-control" disabled value="VIP"></td></tr></tbody></table></div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button></div></div></div></div>`;
}

function roomsHallPlanningContent() {
  const hallGroups = [
    { name: 'Main Hall', color: '#78CD51', tables: [['1', 'circle'], ['2', 'square'], ['3', 'rect'], ['4', 'circle']] },
    { name: 'VIP սրահ', color: '#5FA8D3', tables: [['5', 'square'], ['6', 'circle'], ['7', 'rect']] },
    { name: 'Terrace', color: '#F8B51C', tables: [['8', 'circle'], ['9', 'square']] },
  ];
  const tableImg = { circle: 't1.png', rect: 't2.png', square: 't3.png' };
  const placedTables = [
    ['Սեղան 1', 'circle', '12%', '18%'],
    ['Սեղան 2', 'square', '34%', '22%'],
    ['Սեղան 3', 'rect', '58%', '19%'],
    ['Սեղան 4', 'circle', '22%', '58%'],
    ['VIP 5', 'square', '48%', '55%'],
    ['Terrace 8', 'circle', '75%', '60%'],
  ];

  return `<div class="hall-plan-page">
    <div class="hall-plan-top">
      <div>
        <a href="rooms-hall.html" class="btn btn-default hall-plan-back"><i class="fa fa-angle-left"></i> Վերադառնալ</a>
        <h2>Հարկի հատակագիծ</h2>
        <p>Select position and size of halls in floor</p>
      </div>
      <button class="btn btn-success save_hall"><i class="fa fa-upload"></i> File upload</button>
    </div>

    <div class="planning_main hall-plan-workspace">
      <aside class="hall-plan-sidebar">
        <div id="accordion">
          ${hallGroups.map((hall, index) => `<section class="hall-plan-group">
            <h3 style="background:${hall.color}!important">${hall.name}(${hall.tables.length})<span class="halls_add plus_icon"><i class="icon-plus"></i></span></h3>
            <div class="ac_content" style="background:${hall.color}40 !important">
              <div class="tables_">
                ${hall.tables.map(([number, type]) => `<div class="hall_table" data-type="${type}">
                  <span>${number}</span>
                  <img class="table_img_list ${type === 'rect' ? 'table_img_list2' : ''}" src="assets/img/tables/${tableImg[type]}" alt="table ${number}">
                </div>`).join('')}
              </div>
            </div>
          </section>`).join('')}
        </div>
      </aside>

      <section class="halls_section tbl zoom" id="show">
        <div class="floor-grid"></div>
        <div class="box-wrapper remove_palnning demo-selection">
          <div class="hall_buttons">
            <button class="save_planning_room"><i class="icon-check"></i> Հաստատել</button>
            <button class="remove_planning_room"><img src="assets/img/icons/trash.svg" alt=""> Ջնջել</button>
          </div>
          <div class="box" style="width: 356px;height: 158px;">
            <div class="dot rotate"></div>
            <div class="dot left-top"></div>
            <div class="dot left-bottom"></div>
            <div class="dot top-mid"></div>
            <div class="dot bottom-mid"></div>
            <div class="dot left-mid"></div>
            <div class="dot right-mid"></div>
            <div class="dot right-bottom"></div>
            <div class="dot right-top"></div>
            <div class="rotate-link"></div>
          </div>
        </div>
        <div class="floor-room floor-room-main">Main Hall</div>
        <div class="floor-room floor-room-vip">VIP սրահ</div>
        <div class="floor-room floor-room-terrace">Terrace</div>
        ${placedTables.map(([name, type, left, top]) => `<div class="placed-table placed-table-${type}" style="left:${left};top:${top}">
          <span>${name}</span>
          <img src="assets/img/tables/${tableImg[type]}" alt="${name}">
        </div>`).join('')}
      </section>
    </div>
  </div>`;
}

function roomsHallTablesContent() {
  const rows = [
    { name: '1', cls: 'blue', fix: '', percent: '10', time: '', delivery: false, cost: false, banquet: '', canEdit: false },
    { name: '10', cls: 'blue', fix: '', percent: '10', time: '', delivery: false, cost: false, banquet: '', canEdit: false },
    { name: '11', cls: 'blue', fix: '', percent: '10', time: '', delivery: false, cost: false, banquet: '', canEdit: false },
    { name: '12', cls: 'green', fix: '', percent: '10', time: '', delivery: false, cost: false, banquet: '', canEdit: true },
    { name: '13', cls: 'red', fix: '', percent: '10', time: '', delivery: false, cost: false, banquet: '', canEdit: false },
    { name: '14', cls: 'blue', fix: '', percent: '10', time: '', delivery: false, cost: false, banquet: '', canEdit: false },
    { name: '15', cls: 'green', fix: '', percent: '10', time: '', delivery: false, cost: false, banquet: '', canEdit: true },
    { name: '16', cls: 'green', fix: '', percent: '10', time: '', delivery: false, cost: false, banquet: '', canEdit: true },
    { name: '2', cls: 'blue', fix: '', percent: '10', time: '', delivery: false, cost: false, banquet: '', canEdit: false },
    { name: '3', cls: 'yellow', fix: '', percent: '10', time: '', delivery: false, cost: false, banquet: '', canEdit: false },
  ];
  const ok = '<i class="icon-ok table-ok"></i>';
  const no = '<i class="icon-remove text-danger"></i>';

  return `<div class="hall-tables-page">
    <div id="add_room_table" class="modal fade" role="dialog" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <button aria-hidden="true" data-dismiss="modal" class="close" type="button">×</button>
            <h4 class="modal-title">Ավելացնել</h4>
          </div>
          <div class="modal-body">
            <div class="form-group m-bot15">
              <label class="col-lg-4 control-label">Անվանում</label>
              <div class="col-lg-8"><input type="text" class="form-control" name="table_name" id="table_name" value="Սեղան 10"></div>
            </div>
            <div class="form-group m-bot15">
              <label class="col-lg-4 control-label">ՀԴՄ բաժին</label>
              <div class="col-lg-8"><input type="number" class="form-control" name="hdm_dep" id="hdm_dep" value="1"></div>
            </div>
            <div class="form-group m-bot15">
              <label class="col-lg-4 control-label">Սեղանի ձևը</label>
              <div class="col-lg-8">
                <select class="form-control" name="planning_table_form" id="planning_table_form">
                  <option>Կլոր</option>
                  <option selected>Քառակուսի</option>
                  <option>Ուղղանկյուն</option>
                </select>
              </div>
            </div>
            <div class="form-group m-bot15">
              <label class="col-lg-4 control-label">Սպասարկման վճարի տիպ</label>
              <div class="col-lg-8">
                <select class="form-control input-sm m-bot15" name="commission_type" id="commission_type">
                  <option>Տոկոսային</option>
                  <option>Հաստատագրված</option>
                  <option>Ժամավճար</option>
                  <option>Առանց տոկոսավճարի</option>
                  <option>Ինքնարժեք</option>
                  <option>Բանկետ</option>
                </select>
              </div>
            </div>
            <div class="form-group m-bot15 mustBeLine">
              <label class="col-lg-4 control-label">Սպասարկման վճարի չափ</label>
              <div class="input-group m-bot15 col-lg-8">
                <input type="text" class="form-control" name="commission_value" id="commission_value" value="10">
                <span id="commission_value_arjeq" class="input-group-addon">%</span>
              </div>
            </div>
            <div class="form-group">
              <label class="col-lg-4 control-label">Առաքում</label>
              <div class="col-lg-8"><label class="checkbox-inline"><input type="checkbox" name="delivery"> Այո</label></div>
            </div>
            <input type="hidden" id="place_id" name="place_id" value="">
            <input type="hidden" id="action" name="action" value="add_table">
            <input type="hidden" id="hallId" name="hallId" value="1">
          </div>
          <div class="modal-footer">
            <button class="finish btn btn-success" id="tableAction">Հաստատել</button>
          </div>
        </div>
      </div>
    </div>

    <div id="del_room_table" class="modal fade" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal_left_and_top_padding40">
        <div class="modal-content">
          <div class="modal-header"><h4 class="modal-title">Ջնջել սեղանը</h4></div>
          <div class="modal-body text-center">
            <legend>Համոզված ե՞ք ջնջել սեղանը</legend>
            <button id="ayo2" type="button" class="btn btn-danger">Հաստատել</button>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-lg-12 pad_320">
        <section class="panel hall-tables-panel">
          <header class="panel-heading">
            <h2>Դրսի սրահ - Առաջին հարկ</h2>
            <div>
              <a href="#add_room_table" data-toggle="modal" class="btn btn-success"><i class="icon-plus"></i> Ավելացնել նոր սեղան</a>
            </div>
          </header>
          <div class="panel-body pad_320">
            <div class="adv-table">
              <div id="example_wrapper" class="dataTables_wrapper" role="grid">
                <div class="dataTables_length" id="example_length">
                  <label>Ցույց տալ
                    <select size="1" name="example_length" aria-controls="example">
                      <option value="10" selected>10</option>
                      <option value="25">25</option>
                      <option value="50">50</option>
                      <option value="100">100</option>
                    </select>
                    գրառում
                  </label>
                </div>
                <div class="dataTables_filter" id="example_filter">
                  <label>Փնտրել: <input type="text" aria-controls="example"></label>
                </div>
                <table class="display table table-bordered table_v dataTable" id="example">
                  <thead>
                    <tr>
                      <th class="sorting">Անվանում</th>
                      <th class="sorting"><img src="assets/img/icons/fix.png" alt=""> Հաստատագրված</th>
                      <th class="sorting"><img src="assets/img/icons/precent.png" alt=""> Տոկոսային</th>
                      <th class="sorting"><img src="assets/img/icons/time.png" alt=""> Ժամավճար</th>
                      <th class="sorting"><img src="assets/img/common/del.png" alt=""> Առաքում</th>
                      <th class="sorting"><img src="assets/img/icons/cost.png" alt=""> Ինքնարժեք</th>
                      <th class="sorting"><img src="assets/img/icons/banquet.png" alt=""> Բանկետ</th>
                      <th>Գործողություններ</th>
                    </tr>
                  </thead>
                  <tbody>
                    ${rows.map(row => `<tr class="${row.cls}" style="color:#fff">
                      <td class="tableName">${row.name}</td>
                      <td class="fixedCharge">${row.fix}</td>
                      <td class="percentCharge">${row.percent}</td>
                      <td class="timeCharge">${row.time}</td>
                      <td class="isDelivery">${row.delivery ? ok : no}</td>
                      <td class="isCost">${row.cost ? ok : no}</td>
                      <td class="banquet">${row.banquet}</td>
                      <td>
                        ${row.canEdit ? `<div class="inTableButtonsContainer">
                          <button class="btn btn-warning btn-xs place_edit inTableIconButton" data-toggle="modal" data-target="#add_room_table"><img src="assets/img/icons/pencil.svg" alt=""></button>
                          <button class="btn btn-danger btn-xs place_delete inTableIconButton" data-toggle="modal" data-target="#del_room_table"><img src="assets/img/icons/trash.svg" alt=""></button>
                        </div>` : ''}
                      </td>
                    </tr>`).join('')}
                  </tbody>
                </table>
                <div class="dataTables_info" id="example_info">Ցուցադրված է 1-ից 10-ը 21 գրառումից</div>
                <div class="dataTables_paginate paging_bootstrap pagination">
                  <ul>
                    <li class="prev disabled"><a href="#">← Նախորդ</a></li>
                    <li class="active"><a href="#">1</a></li>
                    <li><a href="#">2</a></li>
                    <li><a href="#">3</a></li>
                    <li class="next disabled"><a href="#">Հաջորդ →</a></li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>
  </div>`;
}

function roomsPrinterErrorContent() {
  const rows = [
    {
      date: '2026-07-03 10:18:24',
      number: '1024',
      place: 'Խոհանոց',
      type: 'Պատվեր',
      user: 'Արամ Սարգսյան',
      lastTry: '2026-07-03 10:19:01',
      tries: '3',
    },
    {
      date: '2026-07-03 10:42:09',
      number: '1025',
      place: 'Բար',
      type: 'Հաշիվ',
      user: 'Նարե Մկրտչյան',
      lastTry: '2026-07-03 10:42:40',
      tries: '2',
    },
    {
      date: '2026-07-03 11:05:36',
      number: '1026',
      place: 'Խոհանոց',
      type: 'Փոփոխություն',
      user: 'Գոռ Մարտիրոսյան',
      lastTry: '2026-07-03 11:06:02',
      tries: '1',
    },
  ];

  const tableRows = rows.map((row, index) => `
                    <tr>
                      <td>${row.date}</td>
                      <td>${row.number}</td>
                      <td>${row.place}</td>
                      <td>${row.type}</td>
                      <td>${row.user}</td>
                      <td>${row.lastTry}</td>
                      <td>${row.tries}</td>
                      <td>
                        <button class="btn btn-sm btn-success" data-id="${index + 1}" data-type-print="printerError" data-to-status="new">Կրկին տպել</button>
                        <button class="btn btn-sm btn-danger" data-id="${index + 1}" data-to-status="success">Չեղարկել</button>
                      </td>
                    </tr>`).join('');

  return `<div class="rooms-printer-error-page">
            <h2 class="pageTitle">Սրահներ/Սեղաններ-Կտրոններ</h2>
            <div class="p_e_header">
              <header class="myPanel">
                <ul class="nav nav-tabs myNav menuButtonsContainer">
                  <li class="active" data-status="error"><a class="notPrintedButton">Չտպված</a></li>
                  <li data-status="success"><a class="printedButton" href="#">Տպված</a></li>
                  <li><a class="fiscalButton" href="rooms-fiscal-error.html">հդմ կտրոններ</a></li>
                </ul>
                <div class="printAndCancelButtons">
                  <button class="btn btn-success printAllButton" data-union-status="new">Տպել բոլորը</button>
                  <button class="btn btn-default cancelAllButton" data-union-status="success">Չեղարկել բոլորը</button>
                </div>
              </header>
            </div>

            <div id="DataTables_Table_0_wrapper" class="dataTables_wrapper form-inline" role="grid">
              <div class="row">
                <div class="col-sm-6">
                  <div class="dataTables_length" id="DataTables_Table_0_length">
                    <label>Ցույց տալ
                      <select name="DataTables_Table_0_length" aria-controls="DataTables_Table_0" class="form-control input-sm">
                        <option value="10" selected>10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                      </select>
                      գրառում
                    </label>
                  </div>
                </div>
                <div class="col-sm-6">
                  <div id="DataTables_Table_0_filter" class="dataTables_filter">
                    <label>Փնտրել:<input type="search" class="form-control input-sm" aria-controls="DataTables_Table_0"></label>
                  </div>
                </div>
              </div>
              <div class="row">
                <div class="col-sm-12">
                  <table class="table table-bordered myTable dataTable table-responsive" id="DataTables_Table_0">
                    <thead>
                    <tr>
                      <th>Ամսաթիվ</th>
                      <th>Կտրոնի համար</th>
                      <th>Պատրաստման վայր</th>
                      <th>Տեսակ</th>
                      <th>Օգտատեր</th>
                      <th>Վերջին փորձի ամսաթիվ</th>
                      <th>Փորձերի քանակ</th>
                      <th><i class="icon-cogs"></i></th>
                    </tr>
                    </thead>
                    <tbody data-purpose="infoTable">${tableRows}
                    </tbody>
                  </table>
                </div>
              </div>
              <div class="row">
                <div class="col-sm-5"><div class="dataTables_info" id="DataTables_Table_0_info">Ցուցադրված է 1-ից 3-ը 3 գրառումից</div></div>
                <div class="col-sm-7">
                  <div class="dataTables_paginate paging_bootstrap pagination">
                    <ul><li class="prev disabled"><a href="#">← Նախորդ</a></li><li class="active"><a href="#">1</a></li><li class="next disabled"><a href="#">Հաջորդ →</a></li></ul>
                  </div>
                </div>
              </div>
            </div>

            <div class="modal fade" id="orderModal" role="dialog" aria-hidden="true">
              <div class="modal-dialog modal-lg">
                <div class="modal-content">
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                  <div class="modal-body">
                    <h4>Կտրոնի տվյալներ</h4>
                    <table class="table table-bordered">
                      <tbody>
                        <tr><th>Կտրոնի համար</th><td>1024</td></tr>
                        <tr><th>Պատրաստման վայր</th><td>Խոհանոց</td></tr>
                        <tr><th>Վիճակ</th><td>չտպված</td></tr>
                      </tbody>
                    </table>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-default pull-left col-xs-5" data-dismiss="modal">Փակել</button>
                    <button class="btn btn-success pull-right btn-save col-xs-5" data-dismiss="modal">Հաստատել</button>
                  </div>
                </div>
              </div>
            </div>
          </div>`;
}

function roomsFiscalErrorContent() {
  const statuses = [
    { id: 10, label: 'Նոր' },
    { id: 20, label: 'Անպատասխան', count: 0 },
    { id: 30, label: 'Տպված' },
    { id: 40, label: 'Չտպված', count: 0 },
    { id: 50, label: 'Չեղարկված' },
    { id: 60, label: 'Վերադարձված' },
  ];

  const rows = [
    {
      id: 418,
      date: '2026-07-03 10:22:18',
      order: '#1024',
      total: '12,400',
      comment: 'ՀԴՄ կտրոնը սպասում է սարքի պատասխանին',
      cashbox: 'Դրամարկղ 1',
      errorCode: 'E-120',
      error: 'ՀԴՄ սարքը չի պատասխանել',
    },
    {
      id: 419,
      date: '2026-07-03 10:58:43',
      order: '#1025',
      total: '8,900',
      comment: 'Անհրաժեշտ է կրկին փորձել',
      cashbox: 'Դրամարկղ 1',
      errorCode: 'E-041',
      error: 'Կապի սխալ',
    },
    {
      id: 420,
      date: '2026-07-03 11:16:05',
      order: 'Կանխավճար',
      total: '21,700',
      comment: 'Ստեղծվել է կանխավճարից',
      cashbox: 'Դրամարկղ 2',
      errorCode: '',
      error: '',
    },
  ];

  const tabs = statuses.map(status => `
                  <li style="text-align: center" class="${status.id === 10 ? 'active ' : ''}filterTab">
                    <a href="rooms-fiscal-error.html?status=${status.id}">${status.label}</a>${status.count ? `
                    <span class="currentQuantity">${status.count}</span>` : status.count === 0 ? `
                    <span class="currentQuantity">0</span>` : ''}
                  </li>`).join('');

  const tableRows = rows.map((row, index) => `
                    <tr>
                      <td></td>
                      <td></td>
                      <td></td>
                      <td></td>
                      <td><input type="checkbox" class="for-fiscal" name="fiscal_group[]" value="${row.id}"></td>
                      <td>${row.id}</td>
                      <td>${row.date}</td>
                      <td><a href="../reports/check.php?id=${1024 + index}" target="_blank">${row.order}</a></td>
                      <td>${row.total}</td>
                      <td>${row.comment}</td>
                      <td>${row.cashbox}</td>
                      <td>${row.errorCode}</td>
                      <td>${row.error}</td>
                      <td>
                        <button class="btn btn-warning retry-print" data-id="${row.id}">Կրկին փորձել</button>
                        <button class="btn btn-danger remove-print" data-id="${row.id}">Հեռացնել հերթից</button>
                      </td>
                    </tr>`).join('');

  return `<div class="rooms-fiscal-error-page">
            <header class="myPanel">
              <ul class="nav nav-tabs myNav menuButtonsContainer">${tabs}
              </ul>
            </header>

            <div class="col-xs-12 clearfix fiscal-filter-row">
              <form role="form" method="get" class="form_btn_pad">
                <input class="hidden" name="status" value="10">
                <div class="input-group input-large col-sm-6 col-xs-12 header_filter" data-date-format="yyyy/mm/dd" id="fiscal-date-filter">
                  <input type="text" class="form-control dpd1" value="2026-07-03" name="start_date">
                  <span class="input-group-addon inputsDivider">ից</span>
                  <input type="text" class="form-control dpd2" value="2026-07-03" name="end_date">
                  <span class="input-group-btn">
                    <button class="btn btn-md btn-info padding5 filterButton" type="submit">Ֆիլտրել</button>
                  </span>
                </div>
              </form>
              <div class="searchField">
                <div id="fiscal_table_filter" class="dataTables_filter">
                  <label>Փնտրել:<input type="search" class="form-control input-sm" aria-controls="fiscal_table"></label>
                </div>
              </div>
            </div>

            <button class="btn btn-sm btn-send checkAllButton" id="checkAll" name="submitData">Նշել բոլորը</button>
            <button class="btn btn-sm btn-warning printChecked fiscal-some-rows" id="printSelected" data-action="retryFiscalReceiptPrinting">Տպել նշվածները</button>
            <button class="btn btn-sm btn-danger removeChecked fiscal-some-rows" id="removeSelected" data-action="cancelFiscalReceiptPrinting">Հեռացնել նշվածները</button>
            <div class="fiscal-export-buttons">
              <button class="btn btn-warning"><i class="fa fa-print"></i> Տպել</button>
              <button class="btn btn-success"><i class="fa fa-file-excel-o"></i> Excel</button>
            </div>

            <div id="fiscal-table-wrapper" class="dataTables_wrapper form-inline tableContainer" role="grid">
              <div class="row">
                <div class="col-sm-12">
                  <table class="table table-bordered mytable myTable dataTable table-responsive" id="fiscal_table">
                    <thead>
                    <tr>
                      <td></td>
                      <td></td>
                      <td></td>
                      <td></td>
                      <td>Նշել</td>
                      <th>ID</th>
                      <th>Ամսաթիվ</th>
                      <th>Կտրոնի համար</th>
                      <th>Կտրոնի գումար</th>
                      <th>ՀԴՄ֊ի մեկնաբանություն</th>
                      <th>Դրամարկղ</th>
                      <th>Խնդրի համար</th>
                      <th>Խնդիր</th>
                      <th><i class="icon-cogs"></i></th>
                    </tr>
                    <tr class="fiscal-column-filters">
                      <td></td>
                      <td></td>
                      <td></td>
                      <td></td>
                      <td><input class="form-control input-sm" placeholder="Փնտրել"></td>
                      <td><input class="form-control input-sm" placeholder="Փնտրել"></td>
                      <td><input class="form-control input-sm" placeholder="Փնտրել"></td>
                      <td><input class="form-control input-sm" placeholder="Փնտրել"></td>
                      <td><input class="form-control input-sm" placeholder="Փնտրել"></td>
                      <td><input class="form-control input-sm" placeholder="Փնտրել"></td>
                      <td><input class="form-control input-sm" placeholder="Փնտրել"></td>
                      <td><input class="form-control input-sm" placeholder="Փնտրել"></td>
                      <td><input class="form-control input-sm" placeholder="Փնտրել"></td>
                      <td></td>
                    </tr>
                    </thead>
                    <tbody>${tableRows}
                    <tfoot>
                    <tr>
                      <th></th>
                      <th></th>
                      <th></th>
                      <th></th>
                      <th>Ընդհանուր</th>
                      <th></th>
                      <th></th>
                      <th></th>
                      <th class="total-sum">43,000</th>
                      <th></th>
                      <th></th>
                      <th></th>
                      <th></th>
                      <th></th>
                    </tr>
                    </tfoot>
                    </tbody>
                  </table>
                </div>
              </div>

              <div class="shownAndPagination row">
                <div class="col-sm-5"><div class="dataTables_info" id="fiscal_table_info">Ցուցադրված է 1-ից 3-ը 3 գրառումից</div></div>
                <div class="col-sm-7">
                  <div class="dataTables_paginate paging_bootstrap pagination">
                    <ul><li class="prev disabled"><a href="#">← Նախորդ</a></li><li class="active"><a href="#">1</a></li><li class="next disabled"><a href="#">Հաջորդ →</a></li></ul>
                  </div>
                </div>
              </div>
            </div>

            <div aria-hidden="true" aria-labelledby="returnPrepaymentFiscalModalLabel" role="dialog"
                 id="returnPrepaymentFiscalModal" class="modal fade"
                 data-endpoint="controllers/fiscalError.php"
                 data-invalid-amount-message="Գումարը սխալ է"
                 data-over-limit-message="Գումարը չի կարող գերազանցել կտրոնի գումարը">
              <div class="modal-dialog">
                <div class="modal-content">
                  <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title" id="returnPrepaymentFiscalModalLabel">Վերադարձ</h4>
                  </div>
                  <div class="modal-body">
                    <input type="hidden" id="returnPrepaymentFiscalId">
                    <div class="form-group">
                      <label for="returnPrepaymentFiscalAmount">Գումար</label>
                      <div class="input-group">
                        <input type="number" step="1" min="0" class="form-control" id="returnPrepaymentFiscalAmount">
                        <span class="input-group-btn">
                          <button type="button" class="btn btn-default" id="returnPrepaymentFiscalMax">Max</button>
                        </span>
                      </div>
                    </div>
                    <div id="returnPrepaymentFiscalError" class="text-danger hide"></div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button>
                    <button type="button" class="btn btn-success" id="acceptReturnPrepaymentFiscal">Հաստատել</button>
                  </div>
                </div>
              </div>
            </div>
          </div>`;
}

function roomsInvisibleOrdersContent() {
  const rows = [
    { id: 10042, date: '2026-07-03 10:18:24' },
    { id: 10057, date: '2026-07-03 11:04:09' },
    { id: 10063, date: '2026-07-03 11:37:51' },
  ];

  const tableRows = rows.map(row => `
                    <tr>
                      <td></td>
                      <td></td>
                      <td></td>
                      <td></td>
                      <td>${row.date}</td>
                      <td>${row.id}</td>
                      <td>
                        <button data-id="${row.id}" class="btn btn-primary transform_order">Տեղափոխել սեղանը</button>
                      </td>
                    </tr>`).join('');

  return `<div class="rooms-invisible-orders-page">
            <div class="col-xs-12 clearfix invisible-orders-toolbar">
              <div class="searchField">
                <div id="invisible_orders_filter" class="dataTables_filter">
                  <label>Փնտրել:<input type="search" class="form-control input-sm" aria-controls="invisible_orders_table"></label>
                </div>
              </div>
            </div>

            <div id="invisible-orders-wrapper" class="dataTables_wrapper form-inline tableContainer" role="grid">
              <div class="row">
                <div class="col-sm-12">
                  <table class="table table-bordered mytable myTable table-responsive" id="invisible_orders_table">
                    <thead>
                    <tr>
                      <td></td>
                      <td></td>
                      <td></td>
                      <td></td>
                      <td>Ամսաթիվ</td>
                      <td>Պատվեր</td>
                      <th><i class="icon-cogs"></i></th>
                    </tr>
                    </thead>
                    <tbody>${tableRows}
                    </tbody>
                  </table>
                </div>
              </div>

              <div class="shownAndPagination row">
                <div class="col-sm-5"><div class="dataTables_info" id="invisible_orders_info">Ցուցադրված է 1-ից 3-ը 3 գրառումից</div></div>
                <div class="col-sm-7">
                  <div class="dataTables_paginate paging_bootstrap pagination">
                    <ul><li class="prev disabled"><a href="#">← Նախորդ</a></li><li class="active"><a href="#">1</a></li><li class="next disabled"><a href="#">Հաջորդ →</a></li></ul>
                  </div>
                </div>
              </div>
            </div>
  </div>`;
}

function roomsKitchenContent() {
  const orders = [
    {
      table: 'Սեղան 4',
      uniq: '1048',
      clientCheck: '27',
      rows: [
        { name: 'Խորոված հավի մսով', count: 2, time: '04:15', hour: '14:38', tone: 'danger', seconds: 255 },
        { name: 'Կարտոֆիլ ֆրի', count: 3, time: '01:08', hour: '14:35', tone: 'alert scale-text', seconds: 68 },
        { name: 'Ամառային աղցան', count: 1, time: '08:42', hour: '14:43', tone: 'warning', seconds: 522 },
      ],
      extras: [
        { label: 'Առանց', value: 'Առանց սոխ, Առանց մայոնեզ' },
        { label: 'Մեկնաբանություն', value: 'Սեղան 4 խնդրել է արագ պատրաստել' },
      ],
    },
    {
      table: 'Սեղան 8',
      uniq: '1049',
      clientCheck: '',
      rows: [
        { name: 'Բուրգեր տավարի մսով', count: 1, time: '12:30', hour: '14:47', tone: 'success', seconds: 750 },
        { name: 'Կեսար աղցան', count: 2, time: '09:16', hour: '14:44', tone: 'warning', seconds: 556 },
        { name: 'Լիմոնադ', count: 4, time: '18:05', hour: '14:53', tone: 'default text-gray', seconds: 1085 },
      ],
      extras: [
        { label: 'Մեկնաբանություն', value: 'Լիմոնադը առանց սառույցի' },
      ],
    },
    {
      table: 'Արագ սնունդ',
      uniq: '1050',
      clientCheck: '31',
      rows: [
        { name: 'Շաուրմա հավի մսով', count: 2, time: '06:44', hour: '14:41', tone: 'danger', seconds: 404 },
        { name: 'Կարտոֆիլ գյուղական', count: 1, time: '14:02', hour: '14:49', tone: 'success', seconds: 842 },
      ],
      extras: [
        { label: 'Առանց', value: 'Առանց կծու սոուս' },
      ],
    },
    {
      table: 'Սեղան 12',
      uniq: '1051',
      clientCheck: '',
      rows: [
        { name: 'Սթեյք սաղմոնով', count: 1, time: '22:10', hour: '14:57', tone: 'default text-gray', seconds: 1330 },
        { name: 'Բրուսկետա', count: 2, time: '07:33', hour: '14:42', tone: 'warning', seconds: 453 },
      ],
      extras: [],
    },
  ];

  const orderCards = orders.map((order, orderIndex) => `
    <div class="col-md-6 col-xs-12 kitchen-order-col">
      <aside class="profile-nav alt kitchen-order" data-json-check='{"${orderIndex + 1}":[${order.rows.map(row => row.count).join(',')}]}'>
        <section class="panel">
          <div class="user-heading alt">
            <h1 class="text-center"><i class=""></i>${order.table} / ${order.uniq}${order.clientCheck ? ` / No.${order.clientCheck}` : ''}</h1>
          </div>
          <table class="nav nav-pills nav-stacked sortable table table-responsive" data-table="menu_group">
            <thead>
              <tr class="sortable_children">
                <td>Անվանում</td>
                <td>Պատվիրված</td>
                <td>Ժամանակ</td>
                <td><i class="icon icon-cogs"></i></td>
              </tr>
            </thead>
            <tbody>
              ${order.rows.map((row, rowIndex) => `
                <tr class="sortable_children text-white ${row.tone} vertical-middle" data-content-id="${orderIndex + 1}${rowIndex + 1}" data-will-be-ready-date="2026-07-03 ${row.hour}:00">
                  <td>${row.name}</td>
                  <td>${row.count}</td>
                  <td data-seconds="${row.seconds}" data-countdown="2026-07-03 ${row.hour}:00">${row.time}</td>
                  <td>
                    <div class="input-group">
                      <input type="text" class="form-control duration time ui-timepicker-input" autocomplete="off" data-hour-min="14:30" data-hour-max="15:30" data-hour="${row.hour}" value="${row.hour}">
                      <span class="btn-success input-group-addon focus-animate done text-success"><i class="icon icon-ok"></i></span>
                    </div>
                  </td>
                </tr>`).join('')}
              ${order.extras.map(extra => `
                <tr class="kitchen-extra-row">
                  <td></td>
                  <td>${extra.label}</td>
                  <td colspan="2">${extra.value}</td>
                </tr>`).join('')}
            </tbody>
          </table>
        </section>
      </aside>
    </div>`).join('');

  return `<section class="kitchen rooms-kitchen-page">
    <div class="row kitchen-orders-row">
      ${orderCards}
    </div>
  </section>`;
}

function reserveContent() {
  const calendar = (id, title, days, reservedDays = []) => {
    return `<section class="panel">
      <div class="panel-body special">
        <div class="reserve-calendar-wrap">
          <table id="${id}" class="reserve-calendar">
            <thead>
              <tr>
                <td><i class="font-40px icon-caret-left text-white"></i></td>
                <td colspan="5" class="text-white">${title}</td>
                <td><i class="font-40px icon-caret-right text-white"></i></td>
              </tr>
            </thead>
            <tbody>
              ${days.map((row, rowIndex) => `<tr>${row.map(day => {
                if (!day) return '<td>&nbsp;</td>';
                const reserved = reservedDays.includes(day);
                return `<td class="${reserved ? 'reserved' : ''}">${day}${reserved ? '<span></span>' : ''}</td>`;
              }).join('')}</tr>`).join('')}
            </tbody>
          </table>
        </div>
      </div>
    </section>`;
  };

  const rows = [
    {
      table: 'Սեղան 8',
      hall: 'Դրսի սրահ',
      date: '03.07.2026 19:30',
      clientName: 'Ծննդյան սեղան',
      client: 'Անի Մկրտչյան',
      phone: '+374 91 22 33 44',
      deposit: '20,000',
      paid: '10,000',
      description: '6 անձ',
      reminder: '03.07.2026 18:30',
      order: '-',
    },
    {
      table: 'Սեղան 12',
      hall: 'Առաջին հարկ',
      date: '04.07.2026 21:00',
      clientName: 'Ընտանեկան',
      client: 'Գոռ Սարգսյան',
      phone: '+374 77 45 67 89',
      deposit: '0',
      paid: '-',
      description: 'Պատուհանի մոտ',
      reminder: '04.07.2026 20:00',
      order: '#1028',
    },
  ];

  const tableRows = rows.map((row, index) => `
              <tr class="gridToTable" data-id="${index + 1}">
                <td>${row.table}</td>
                <td>${row.hall}</td>
                <td>${row.date}</td>
                <td>${row.clientName}</td>
                <td>${row.client}</td>
                <td>${row.phone}</td>
                <td>${row.deposit}</td>
                <td>${row.paid}</td>
                <td>${row.description}</td>
                <td>${row.reminder}</td>
                <td>${row.order}</td>
              <td class="cog_btns_td historyGridActions">
                  <div class="flexBtns inTableButtonsContainer">
                    <button class="btn btn-xs btn-info pay-btn"><i class="icon-money"></i></button>
                    <button class="cancel_reservation btn btn-danger btn-xs inTableIconButton" data-toggle="modal" data-target="#deleteReserve"><i class="icon-trash"></i></button>
                    <button class="edit_reservation_grid btn btn-warning btn-xs inTableIconButton" data-toggle="modal" data-target="#addReserveModal"><i class="icon-pencil"></i></button>
                    <button class="btn btn-success btn-xs activate"><i class="icon-ok"></i></button>
                  </div>
                </td>
              </tr>`).join('');
  const gridInfo = rows.length
    ? `Ցուցադրված է 1-ից ${rows.length}-ը ${rows.length} գրառումից`
    : 'Ցուցադրված է 0-ից 0-ը 0 գրառումից';
  const gridPagination = rows.length
    ? '<div class="dataTables_paginate paging_bootstrap pagination"><ul><li class="prev disabled"><a href="#">← Նախորդ</a></li><li class="active"><a href="#">1</a></li><li class="next disabled"><a href="#">Հաջորդ →</a></li></ul></div>'
    : '';

  return `<section id="addReservationSection" class="reserve-page">
    <div class="calendars">
      ${calendar('calendar2', 'Հուլիս 2026', [
        ['', '', 1, 2, 3, 4, 5],
        [6, 7, 8, 9, 10, 11, 12],
        [13, 14, 15, 16, 17, 18, 19],
        [20, 21, 22, 23, 24, 25, 26],
        [27, 28, 29, 30, 31, '', ''],
        ['', '', '', '', '', '', ''],
      ], [3])}
      ${calendar('calendar1', 'Օգոստոս 2026', [
        ['', '', '', '', '', 1, 2],
        [3, 4, 5, 6, 7, 8, 9],
        [10, 11, 12, 13, 14, 15, 16],
        [17, 18, 19, 20, 21, 22, 23],
        [24, 25, 26, 27, 28, 29, 30],
        [31, '', '', '', '', '', ''],
      ], [4])}
      ${calendar('calendar3', 'Սեպտեմբեր 2026', [
        ['', 1, 2, 3, 4, 5, 6],
        [7, 8, 9, 10, 11, 12, 13],
        [14, 15, 16, 17, 18, 19, 20],
        [21, 22, 23, 24, 25, 26, 27],
        [28, 29, 30, '', '', '', ''],
        ['', '', '', '', '', '', ''],
      ], [])}
    </div>

    <div class="col-xs-12 order_btns_">
      <button class="btn btn-block btn-success" data-toggle="modal" data-target="#addReserveModal" id="btn_add_modal">
        <img src="assets/img/icons/plusIcon.svg" alt=""> Ավելացնել
      </button>
      <a class="btn btn-block btn-info" href="reserve.html">Բոլորը</a>
      <a class="btn btn-block btn-default" href="reserve-history.html">Պատմություն</a>
    </div>

    <a class="dt-button buttons-excel buttons-html5 pull-right reserve-excel" tabindex="0">
      <span><button class="btn btn-primary"><img src="assets/img/icons/ExcelLogo.svg" alt=""> Excel</button></span>
    </a>
    <br>

    <div id="reserve-grid-wrapper" class="dataTables_wrapper form-inline">
      <div class="row reserve-grid-top">
        <div class="col-sm-6">
          <div class="dataTables_length">
            <label>Ցույց տալ
              <select class="form-control input-sm"><option selected>10</option><option>25</option><option>50</option></select>
              գրառում
            </label>
          </div>
        </div>
        <div class="col-sm-6">
          <div class="dataTables_filter">
            <label>Փնտրել:<input type="search" class="form-control input-sm"></label>
          </div>
        </div>
      </div>
      <div class="reserve-table-scroll">
        <table class="table table-striped table-bordered" id="reserveGridTable">
          <thead>
            <tr>
              <th>Սեղան</th>
              <th>Սրահ</th>
              <th>Ժամանակ</th>
              <th>Հաճախորդի Նկարագրություն</th>
              <th>Հաճախորդ</th>
              <th>Հեռախոսահամար</th>
              <th>Կանխավճար</th>
              <th>Վճարված կանխավճար</th>
              <th>Նկարագրություն</th>
              <th>Հիշեցնել</th>
              <th>Պատվեր</th>
              <th><i class="fa fa-cogs" aria-hidden="true"></i></th>
            </tr>
            <tr class="filters">
              ${Array.from({ length: 11 }).map(() => '<td><input class="form-control"></td>').join('')}
              <td></td>
            </tr>
          </thead>
          <tbody>${tableRows || '<tr><td colspan="12"><div class="empty">Ոչ մի արդյունք չի գտնվել:</div></td></tr>'}
          </tbody>
        </table>
      </div>
      <div class="row reserve-grid-bottom">
        <div class="col-sm-5"><div class="dataTables_info">${gridInfo}</div></div>
        <div class="col-sm-7">${gridPagination}</div>
      </div>
    </div>

    <input type="hidden" value='{"dates":["2026-07-03","2026-08-04"],"count":{"2026-7-3":1,"2026-8-4":1}}' id="dates">

    <div id="addReserveModal" class="modal fade" role="dialog">
      <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Ավելացնել ամրագրում</h4></div>
        <div class="modal-body"><div class="form-horizontal">
          <div class="form-group"><label class="control-label col-sm-4">Սեղան:</label><div class="col-sm-8"><select class="form-control" id="table_select"><optgroup label="Դրսի սրահ"><option>Սեղան 8</option><option>Սեղան 12</option></optgroup></select></div></div>
          <div class="form-group"><label class="control-label col-sm-4">Հաճախորդի Նկարագրություն:</label><div class="col-sm-8"><input type="text" class="form-control" id="reserve_client_name"></div></div>
          <div class="form-group"><label class="control-label col-sm-4">Հաճախորդ:</label><div class="col-sm-8"><select class="form-control client-select2"><option>Հաճախորդ</option><option>Անի Մկրտչյան</option></select></div></div>
          <div class="form-group"><label class="control-label col-sm-4">Հեռախոսահամար:</label><div class="col-sm-8"><input type="text" class="form-control" id="phone"></div></div>
          <div class="form-group"><label class="control-label col-sm-4">Կանխավճար:</label><div class="col-sm-8"><input type="number" class="form-control" id="reserve_deposit"></div></div>
          <div class="form-group"><label class="control-label col-sm-4">Նկարագրություն:</label><div class="col-sm-8"><input type="text" class="form-control" id="reserve_description"></div></div>
          <div class="form-group"><label class="control-label col-sm-4">Ամսաթիվ:</label><div class="col-sm-8"><div id="datetimeContainer"><div class="col-xs-6 datetimeMin" id="datepicker"><input id="timepicker4" readonly type="text" class="form-control dpd1" value="2026-07-03"></div><div class="col-xs-6 bootstrap-timepicker datetimeMin" id="timepicker"><input id="timepicker2" type="text" readonly class="form-control timepicker-24 tp1 border-radius0" value="19:30"></div></div></div></div>
          <div class="form-group"><label class="control-label col-sm-4">Հիշեցնել:</label><div class="col-sm-4"><div class="bootstrap-timepicker datetimeMin" id="timepicker1"><input id="timepicker3" type="text" readonly class="form-control timepicker-24 tp1 border-radius0" value="18:30"></div></div></div>
          <input type="hidden" id="reservation">
        </div></div>
        <div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button type="button" class="btn btn-success">Հաստատել</button></div>
      </div></div>
    </div>

    <div id="deleteReserve" class="modal fade" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Ջնջել ամրագրումը</h4></div><div class="modal-body"><p>Ջնջե՞լ ընտրված ամրագրումը</p></div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-danger pull-right" id="deleteReserveSubmit">Հաստատել</button></div></div></div></div>
    <div id="alertReserve" class="modal fade" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-body"><p>Տեղափոխությունը կատարվեց</p></div><div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Փակել</button></div></div></div></div>
    <div id="payDepositModal" class="modal fade" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-body"><div class="form-group"><label>Գումար</label><input class="form-control" type="number" disabled readonly id="depositToPay" value="10000"></div><div class="form-group"><label>Դրամարկղ</label><select class="form-control" id="cashboxId"><option>Դրամարկղ 1</option></select></div><input type="hidden" id="orderIdForPayment"></div><div class="modal-footer"><button type="button" class="pull-left btn btn-default" data-dismiss="modal">Փակել</button><button class="btn btn-success pull-right" id="payDep">Վճարել</button></div></div></div></div>
    <div aria-hidden="true" aria-labelledby="myModalLabel" role="dialog" id="staff-modal" class="modal fade"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Ընտրել աշխատակցին</h4></div><div class="modal-body form-horizontal"><div class="form-group"><label class="control-label col-sm-2">Աշխատակից՝</label><div class="col-sm-10"><select class="form-control select-staff"><option>Արամ Սարգսյան</option></select></div></div></div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-success pull-right select-staff-submit">Հաստատել</button></div></div></div></div>
  </section>`;
}

function reserveHistoryContent() {
  const rows = [
    {
      table: '10',
      hall: 'Երկրորդ հարկ',
      date: '2026.06.10 19:30',
      clientName: 'Արթուր',
      phone: '',
      description: '',
      deposit: '0',
      reminder: '2026.06.10 18:30',
      status: 'Պասիվ',
      editable: true,
    },
    {
      table: '2',
      hall: 'Դրսի սրահ',
      date: '2026.06.05 19:00',
      clientName: 'Rashad',
      phone: '043757480',
      description: '',
      deposit: '0',
      reminder: '2026.06.05 18:00',
      status: 'Պասիվ',
      editable: true,
    },
    { table: '2', hall: 'Դրսի սրահ', date: '2026.06.05 11:00', clientName: 'Եկատերինա', phone: '', description: '', deposit: '0', reminder: '2026.06.05 10:00', status: 'Պասիվ', editable: true },
    { table: '9', hall: 'Դրսի սրահ', date: '2026.05.22 13:00', clientName: '', phone: '', description: '', deposit: '0', reminder: '2026.05.22 12:00', status: 'Պասիվ', editable: true },
    { table: '11', hall: 'Դրսի սրահ', date: '2026.05.22 12:59', clientName: '', phone: '', description: '', deposit: '0', reminder: '2026.05.22 11:59', status: 'Պասիվ', editable: true },
    { table: '10', hall: 'Երկրորդ հարկ', date: '2026.05.20 21:30', clientName: 'Միշա', phone: '098828906', description: '12-15 հոգի', deposit: '0', reminder: '2026.05.20 20:30', status: 'Պասիվ', editable: true },
    { table: '9', hall: 'Դրսի սրահ', date: '2026.05.18 18:00', clientName: 'Ալեքսանդրա', phone: '', description: '2', deposit: '0', reminder: '2026.05.18 17:00', status: 'Պասիվ', editable: true },
    { table: '4', hall: 'Երկրորդ հարկ', date: '2026.05.18 18:00', clientName: 'Նունե', phone: '091958678', description: 'Nune', deposit: '0', reminder: '2026.05.18 17:00', status: 'Պասիվ', editable: true },
    { table: '10', hall: 'Երկրորդ հարկ', date: '2026.05.14 19:00', clientName: 'Հր հովիկ', phone: '', description: '10 անձ', deposit: '0', reminder: '2026.05.14 18:00', status: 'Պասիվ', editable: true },
    { table: '11', hall: 'Դրսի սրահ', date: '2026.05.12 18:30', clientName: 'Anya', phone: '033392558', description: '6 per, եթե եղանակը լավ լինի դուրսը, եթե չէ ներսում', deposit: '0', reminder: '2026.05.12 17:30', status: 'Պասիվ', editable: true },
    { table: '4', hall: 'Դրսի սրահ', date: '2026.05.06 12:00', clientName: '', phone: '', description: 'բոլորի պատուհանին առանձին սեղան', deposit: '0', reminder: '2026.05.06 11:00', status: 'Պասիվ', editable: true },
    { table: '8', hall: 'Դրսի սրահ', date: '2026.04.24 10:30', clientName: 'Արմինե', phone: '', description: 'վեց հոգի դրսում', deposit: '0', reminder: '2026.04.24 09:30', status: 'Պասիվ', editable: true },
  ];

  const tableRows = rows.map((row, index) => `
                    <tr class="gridToTable" data-id="${index + 1}" data-table="${row.table}" data-hall="${row.hall}">
                      <td>${row.table}</td>
                      <td>${row.hall}</td>
                      <td>${row.date}</td>
                      <td>${row.clientName}</td>
                      <td>${row.phone}</td>
                      <td>${row.description}</td>
                      <td>${row.deposit}</td>
                      <td>${row.reminder}</td>
                      <td>${row.status}</td>
                      <td class="cog_btns_td historyGridActions">
                        ${row.editable ? `<div class="flexBtns inTableButtonsContainer">
                          <button class="edit_reservation_grid btn btn-warning btn-xs inTableIconButton" data-toggle="modal" data-target="#addReserveModal"><img src="assets/img/icons/pencil.svg" alt=""></button>
                        </div>` : ''}
                      </td>
                    </tr>`).join('');

  return `<div class="reserve-page reserve-history-page">
    <div class="panel panel_back_btn">
      <div class="panel-heading">
        <a href="reserve.html" class="btn-warning pull-left back"><i class="icon-chevron-left"></i> Վերադառնալ</a>
        <div class="check_date">
          <div id="datetimeContainer">
            <div class="col-xs-6 datetimeMin">
              <input readonly type="text" value="2026-07-03" class="form-control dpd1" placeholder="yyyy/mm/dd">
            </div>
          </div>
          <button class="btn btn-success" id="dp"><i class="icon-check"></i></button>
        </div>
      </div>
    </div>

    <section id="addReservationSection">
      <div class="col-xs-12">
        <div class="table-responsive special-scroll-2">
          <a class="dt-button buttons-excel buttons-html5 pull-right reserve-excel" tabindex="0">
            <span><button class="btn btn-primary excelButton"><img src="assets/img/icons/ExcelLogo.svg" alt=""> Excel</button></span>
          </a>
          <br>

          <div id="reserve-history-grid-wrapper" class="dataTables_wrapper form-inline">
            <div class="row reserve-grid-top reserve-history-grid-top">
              <div class="col-sm-6">
                <div class="dataTables_length">
                  <label>Ցույց տալ
                    <select class="form-control input-sm"><option selected>10</option><option>25</option><option>50</option></select>
                    գրառում
                  </label>
                </div>
              </div>
              <div class="col-sm-6"></div>
            </div>
            <div class="reserve-table-scroll">
              <table class="table table-striped table-bordered reserve-data-table" id="reserveHistoryGridTable">
                <thead>
                  <tr>
                    <th>Սեղան</th>
                    <th>Սրահ</th>
                    <th>Ժամանակ</th>
                    <th>Հաճախորդի Նկարագրություն</th>
                    <th>Հեռախոսահամար</th>
                    <th>Նկարագրություն</th>
                    <th>Կանխավճար</th>
                    <th>Հիշեցնել</th>
                    <th>Կարգավիճակ</th>
                    <th class="cogs"><i class="fa fa-cogs" aria-hidden="true"></i></th>
                  </tr>
                  <tr class="filters">
                    ${Array.from({ length: 8 }).map(() => '<td><input class="form-control"></td>').join('')}
                    <td class="dropDown_type hdm"><select class="form-control"><option></option><option>Ակտիվացված</option><option>Պասիվ</option></select></td>
                    <td></td>
                  </tr>
                </thead>
                <tbody>${tableRows}</tbody>
              </table>
            </div>
            <div class="row reserve-grid-bottom">
              <div class="col-sm-5"><div class="dataTables_info">Ցուցադրված է 1-ից 10-ը ${rows.length} գրառումից</div></div>
              <div class="col-sm-7"><div class="dataTables_paginate paging_bootstrap pagination"><ul><li class="prev disabled"><a href="#">← Նախորդ</a></li><li class="active"><a href="#">1</a></li><li class="next disabled"><a href="#">Հաջորդ →</a></li></ul></div></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <div id="addReserveModal" class="modal fade" role="dialog">
      <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Ավելացնել ամրագրում</h4></div>
        <div class="modal-body"><div class="form-horizontal">
          <div class="form-group"><label class="control-label col-sm-4">Սեղան:</label><div class="col-sm-8"><select class="form-control" id="history_table_select"><optgroup label="Դրսի սրահ"><option>Սեղան 8</option><option>Սեղան 12</option></optgroup></select></div></div>
          <div class="form-group"><label class="control-label col-sm-4">Հաճախորդի Նկարագրություն:</label><div class="col-sm-8"><input type="text" class="form-control" id="history_client_name"></div></div>
          <div class="form-group"><label class="control-label col-sm-4">Հեռախոսահամար:</label><div class="col-sm-8"><input type="text" class="form-control" id="history_phone"></div></div>
          <div class="form-group"><label class="control-label col-sm-4">Կանխավճար:</label><div class="col-sm-8"><input type="number" class="form-control" id="history_deposit"></div></div>
          <div class="form-group"><label class="control-label col-sm-4">Նկարագրություն:</label><div class="col-sm-8"><input type="text" class="form-control" id="history_description"></div></div>
        </div></div>
        <div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button></div>
      </div></div>
    </div>
  </div>`;
}

function genericContent(page) {
  return `<section class="panel template-under-review"><header class="panel-heading"><h3>${page.title}</h3></header><div class="panel-body"><p class="desc">Separate static page scaffold. Source sidebar entry${page.parent ? `: ${page.parent}` : ''}. This page is ready for the next pass after dashboard approval.</p><div class="row"><div class="col-md-4"><section class="panel"><header class="panel-heading">Ֆիլտր</header><div class="panel-body"><input class="form-control" placeholder="Որոնում"><button class="btn btn-info m-t-10">Ֆիլտրել</button></div></section></div><div class="col-md-8"><section class="panel"><header class="panel-heading">Տվյալներ</header><table class="table table-bordered"><thead><tr><th>#</th><th>Անվանում</th><th>Վիճակ</th><th></th></tr></thead><tbody><tr><td>1</td><td>${page.title}</td><td><span class="label label-info">demo</span></td><td><button class="btn btn-xs btn-warning get-edit">Փոփոխել</button></td></tr></tbody></table></section></div></div></div></section>`;
}

function companyContent() {
  const companies = [
    ['Արարատ Ֆուդ ՍՊԸ', '0001', 'ՍՊԸ', '010 24 58 77', '091 24 58 77', 'Արմեն'],
    ['Fresh Market', '0002', 'ԱՁ', '010 55 10 80', '093 55 10 80', 'Սոնա'],
    ['Միլք Գրուպ', '0003', 'ՓԲԸ', '010 62 14 45', '099 62 14 45', 'Կարեն'],
    ['Coffee Import', '0004', 'ՍՊԸ', '011 20 44 12', '077 20 44 12', 'Լիլիթ'],
  ];
  const fields = [
    ['Ընկերության անվանում', 'firm_name'],
    ['Կոդ', 'code'],
    ['Իրավաբանական տիպ', 'firm_type_of', 'select'],
    ['ՀՎՀՀ', 'hvhh'],
    ['Հաշվե Համար (ՀՀ)', 'statements'],
    ['Բանկային Հաշվե Համար', 'bank_statements'],
    ['Իրավաբանական հասցե', 'legal_address'],
    ['Գործունեության հասցե', 'business_address'],
    ['Քաղ. հեռախոսահամար', 'phone'],
    ['Բջջ. հեռախոսահամար', 'mob_phone'],
    ['Էլ-փոստ', 'email'],
    ['Կոնտակտային անձ', 'contact_person'],
  ];
  const editValues = {
    firm_name: 'Արարատ Ֆուդ ՍՊԸ',
    code: '0001',
    hvhh: '01234567',
    statements: '1570000000000000',
    bank_statements: 'AM00000000000000000000',
    legal_address: 'Երևան',
    business_address: 'Երևան, Կենտրոն',
    phone: '010 24 58 77',
    mob_phone: '091 24 58 77',
    email: 'info@example.com',
    contact_person: 'Արմեն',
  };
  const formFields = (values = {}) => fields.map(([label, name, type]) => `<div class="form-group"><label class="col-md-4 control-label">${label}</label><div class="col-md-8">${
    type === 'select'
      ? `<select class="form-control ${name}"><option${values[name] === 'ՍՊԸ' || !values[name] ? ' selected' : ''}>ՍՊԸ</option><option${values[name] === 'ՓԲԸ' ? ' selected' : ''}>ՓԲԸ</option><option${values[name] === 'ԱՁ' ? ' selected' : ''}>ԱՁ</option></select>`
      : `<input type="text" class="form-control ${name}"${values[name] ? ` value="${values[name]}"` : ''}${name === 'firm_name' && !values[name] ? ' autofocus' : ''}>`
  }</div></div>`).join('');
  const rows = companies.map(company => `<tr>
      <td><a class="text-primary" href="company-expenses.html">${company[0]}</a></td>
      <td>${company[1]}</td>
      <td>${company[2]}</td>
      <td>${company[3]}</td>
      <td>${company[4]}</td>
      <td>${company[5]}</td>
      <td class="cog_btns_td"><div class="flexBtns">
        <a href="#editCompanyModal" data-toggle="modal" class="btn btn-warning btn-xs inTableIconButton"><img src="assets/img/icons/pencil.svg" alt=""></a>
        <a href="#deleteCompanyModal" data-toggle="modal" class="btn btn-danger btn-xs inTableIconButton"><img src="assets/img/icons/trash.svg" alt=""></a>
        <a href="company-expenses.html" class="btn btn-info btn-xs inTableIconButton"><i class="icon-info-sign"></i></a>
      </div></td>
    </tr>`).join('');

  return `<div class="company-page">
    <div class="row">
      <div class="col-lg-12 pad_320">
        <section class="panel company-panel">
          <header class="panel-heading company-panel-heading">
            <button href="#addCompanyModal" data-toggle="modal" type="button" class="btn btn-success">Ավելացնել ընկերություն</button>
          </header>
          <div class="panel-body pad_320 company-panel-body">
            <div class="company-table-toolbar">
              <div class="company-page-size"><label>Ցուցադրել <select class="form-control input-sm"><option>10</option><option>20</option><option>50</option></select> տող</label></div>
              <a class="dt-button buttons-excel buttons-html5" tabindex="0"><span><button class="btn btn-primary excelButton" type="button"><img src="assets/img/icons/ExcelLogo.svg" alt=""> Excel</button></span></a>
            </div>
            <div class="company-table-wrap">
              <table class="table table-striped table-bordered" id="companyGridTable">
                <thead>
                  <tr><th>Ընկերության անվանում</th><th>Կոդ</th><th>Իրավաբանական տիպ</th><th>Քաղ. հեռախոսահամար</th><th>Բջջ. հեռախոսահամար</th><th>Կոնտակտային անձ</th><th class="cogs"><i class="fa fa-cogs" aria-hidden="true"></i></th></tr>
                  <tr class="filters">${Array.from({ length: 6 }).map(() => '<td><input type="text" class="form-control" placeholder=""></td>').join('')}<td></td></tr>
                </thead>
                <tbody>${rows}</tbody>
              </table>
            </div>
          </div>
        </section>
      </div>
    </div>
  </div>
  <div id="addCompanyModal" class="modal fade company-form-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Ավելացնել նոր ընկերություն</h4></div><div class="modal-body"><form class="form-horizontal">${formFields()}</form></div><div class="modal-footer"><button class="btn btn-success">Հաստատել</button></div></div></div></div>
  <div id="editCompanyModal" class="modal fade company-form-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Փոփոխել ընկերությունը</h4></div><div class="modal-body"><form class="form-horizontal">${formFields({ ...editValues, firm_type_of: 'ՍՊԸ' })}</form></div><div class="modal-footer"><button class="btn btn-success">Հաստատել</button></div></div></div></div>
  <div id="deleteCompanyModal" class="modal fade" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Ջնջել ընկերությունը</h4></div><div class="modal-body"><h4 class="text-center">Դուք համոզված ե՞ք, որ ցանկանում էք ջնջել ընկերությունը</h4></div><div class="modal-footer"><button class="btn btn-danger">Հաստատել</button></div></div></div></div>`;
}

function companyExpensesContent() {
  const rows = [
    ['2026-07-10', 'Արարատ Ֆուդ ՍՊԸ', '135429', '125000', '25000', '100000', false],
    ['2026-07-08', 'Fresh Market', '135431', '68000', '68000', '0', true],
    ['2026-07-05', 'Միլք Գրուպ', '135433', '91500', '30000', '61500', false],
    ['2026-07-02', 'Coffee Import', '135583', '43000', '0', '43000', false],
  ];
  const tableRows = rows.map(row => `<tr>
      <td>${row[0]}</td>
      <td>${row[1]}</td>
      <td><a href="store-document-submitted.html">Document #${row[2]}</a></td>
      <td>${row[3]}</td>
      <td>${row[4]}</td>
      <td>${row[5]}</td>
      <td class="cog_btns_td"><div class="flexBtns">${row[6] ? 'Մարված <i class="icon-ok"></i>' : `<button class="btn btn-xs btn-success pay" value="${row[5]}" data-toggle="modal" data-target="#payModal">Մարել</button>`}</div></td>
    </tr>`).join('');

  return `<div class="company-expenses-page">
    <section class="panel company-expenses-panel">
      <form method="get" class="company-expenses-filters">
        <button type="button" class="btn btn-success" name="filter" value="payed">Մարած</button>
        <button type="button" class="btn btn-warning" name="filter" value="debt">Չմարած</button>
        <button type="button" class="btn btn-default" name="filter" value="all">Բոլորը</button>
      </form>
      <div class="panel-body company-expenses-body">
        <div class="company-expenses-toolbar">
          <div class="company-expenses-page-size"><label>Ցույց տալ <select class="form-control input-sm"><option selected>10</option><option>25</option><option>50</option></select> գրառում</label></div>
          <a class="dt-button buttons-excel buttons-html5" tabindex="0"><span><button class="btn btn-primary excelButton" type="button"><img src="assets/img/icons/ExcelLogo.svg" alt=""> Excel</button></span></a>
        </div>
        <div class="company-expenses-table-wrap">
          <table class="table table-striped table-bordered" id="companyGridTable">
            <thead>
              <tr><th>Ամսաթիվ</th><th>Մատակարար</th><th>Փաստաթուղթ</th><th>Պարտք</th><th>Մարված</th><th>Մնացորդ</th><th class="cogs"><i class="fa fa-cogs" aria-hidden="true"></i></th></tr>
              <tr class="filters">${Array.from({ length: 6 }).map(() => '<td><input type="text" class="form-control" placeholder=""></td>').join('')}<td></td></tr>
            </thead>
            <tbody>${tableRows}</tbody>
            <tfoot><tr><td>Total</td><td></td><td></td><td>327500</td><td>123000</td><td>204500</td><td></td></tr></tfoot>
          </table>
        </div>
        <button class="btn btn-md btn-success payAll" type="button" value="204500" data-toggle="modal" data-target="#payModalAll">Մարել ամբողջը</button>
      </div>
    </section>
    <div id="payModal" class="modal fade company-pay-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Մարել պարտքը</h4></div><div class="modal-body form-horizontal"><div class="form-group"><label class="control-label col-sm-3">Գումար:</label><div class="col-sm-9"><input type="number" class="form-control" name="payed" value="100000"></div></div><div class="form-group"><label class="control-label col-sm-3">Դրամարկղ:</label><div class="col-sm-9"><select class="form-control" name="cashbox"><option>Ընդհանուր</option><option>Կանխիկ</option><option>Բանկային</option><option>Մատակարար</option></select></div></div></div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-success pull-right paySubmit">Հաստատել</button></div></div></div></div>
    <div id="payModalAll" class="modal fade company-pay-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Մարել ամբողջ պարտքը</h4></div><div class="modal-body form-horizontal"><div class="form-group"><label class="control-label col-sm-3">Գումար:</label><div class="col-sm-9"><input type="number" class="form-control" name="payed" value="204500"></div></div><div class="form-group"><label class="control-label col-sm-3">Դրամարկղ:</label><div class="col-sm-9"><select class="form-control" name="cashbox"><option>Ընդհանուր</option><option>Կանխիկ</option><option>Բանկային</option><option>Մատակարար</option></select></div></div></div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-success pull-right paySubmitAll">Հաստատել</button></div></div></div></div>
  </div>`;
}

function clientsContent() {
  const clients = [
    ['1', 'Անի Մկրտչյան', 'Երևան, Կենտրոն', '091 22 33 44', 'ani@example.com', 'Արարատ Ֆուդ ՍՊԸ', 'Մենեջեր', '1994-07-10', 'AN123456', 'Իգական', false],
    ['2', 'Գոռ Սարգսյան', 'Աբովյան 12', '093 45 67 89', 'gor@example.com', 'Fresh Market', 'Խոհարար', '1989-03-18', 'AV987654', 'Արական', true],
    ['3', 'Լիլիթ Հարությունյան', 'Մաշտոց 20', '077 11 22 33', 'lilit@example.com', 'Միլք Գրուպ', 'Հաշվապահ', '1991-11-04', 'AM456789', 'Իգական', false],
    ['4', 'Արամ Պետրոսյան', 'Կոմիտաս 8', '099 80 70 60', 'aram@example.com', 'Coffee Import', 'Մատակարար', '1985-01-26', 'AT234567', 'Արական', false],
  ];
  const rows = clients.map(row => `<tr>
      <td><input type="checkbox" class="for-sms" value="${row[0]}"${row[10] ? ' checked' : ''}></td>
      <td><a class="text-primary" href="clients-expenses.html">${row[1]}</a></td>
      <td>${row[2]}</td>
      <td>${row[3]}</td>
      <td>${row[4]}</td>
      <td>${row[5]}</td>
      <td>${row[6]}</td>
      <td>${row[7]}</td>
      <td>${row[8]}</td>
      <td>${row[9]}</td>
      <td class="cog_btns_td"><div class="flexBtns"><a href="#editClient" data-toggle="modal" class="btn btn-warning btn-xs get-edit-client inTableIconButton"><img src="assets/img/icons/pencil.svg" alt=""></a><a href="#deleteClient" data-toggle="modal" class="btn btn-danger btn-xs delete-client inTableIconButton"><img src="assets/img/icons/trash.svg" alt=""></a></div></td>
    </tr>`).join('');
  const companies = ['Արարատ Ֆուդ ՍՊԸ', 'Fresh Market', 'Միլք Գրուպ', 'Coffee Import'];
  const companyOptions = companies.map((company, index) => `<option${index === 0 ? ' selected' : ''}>${company}</option>`).join('');
  const clientFields = (values = {}) => `<div class="form-group"><label class="control-label col-sm-4">Անուն:</label><div class="col-sm-8"><input type="text" required class="form-control name" name="name"${values.name ? ` value="${values.name}"` : ''}></div></div>
    <div class="form-group"><label class="control-label col-sm-4">Հեռախոսահամար:</label><div class="col-sm-8"><input type="${values.name ? 'number' : 'text'}" required class="form-control phone" name="phone"${values.phone ? ` value="${values.phone}"` : ''}></div></div>
    <div class="form-group"><label class="control-label col-sm-4">Հասցե:</label><div class="col-sm-8"><input type="text" class="form-control address" name="address"${values.address ? ` value="${values.address}"` : ''}></div></div>
    <div class="form-group"><label class="control-label col-sm-4">Էլ․ Հասցե:</label><div class="col-sm-8"><input type="text" class="form-control email" name="email"${values.email ? ` value="${values.email}"` : ''}></div></div>
    <div class="form-group"><label class="control-label col-sm-4">Մասնագիտւթյուն:</label><div class="col-sm-8"><input type="text" class="form-control profession" name="profession"${values.profession ? ` value="${values.profession}"` : ''}></div></div>
    <div class="form-group"><label class="control-label col-sm-4">Ծննդյան ամսաթիվ:</label><div class="col-sm-8"><input type="date" class="form-control birthday" name="birthday"${values.birthday ? ` value="${values.birthday}"` : ''}></div></div>
    <div class="form-group"><label class="control-label col-sm-4">Անձը հաստատող փաստաթուղթ:</label><div class="col-sm-8"><input type="text" class="form-control identificationDocument" name="identificationDocument"${values.doc ? ` value="${values.doc}"` : ''}></div></div>
    <div class="form-group"><label class="control-label col-sm-4">ՀՎՀՀ:</label><div class="col-sm-8"><input type="text" class="form-control hvhh" name="hvhh"${values.hvhh ? ` value="${values.hvhh}"` : ''}></div></div>
    ${values.name ? '<input type="hidden" name="id" id="client-id" value="1">' : ''}
    <div class="form-group"><label class="control-label col-sm-4">Սեռ:</label><div class="col-sm-8"><select name="sex" class="form-control sex"><option value="0"${values.sex !== 'Արական' ? ' selected' : ''}>Իգական</option><option value="1"${values.sex === 'Արական' ? ' selected' : ''}>Արական</option></select></div></div>
    <div class="form-group"><label class="control-label col-sm-4">Ընկերություն:</label><div class="col-sm-8"><select class="form-control select2 company client" name="${values.name ? 'company' : 'moved'}"><option value="0">--Ընկերություն--</option>${companyOptions}</select></div></div>
    <div class="form-group"><label class="control-label col-sm-4">Միջնորդավորված վաճառքի տոկոս:</label><div class="col-sm-8"><input type="text" class="form-control mediated-percent" name="mediatedPercent"${values.percent ? ` value="${values.percent}"` : ''}></div></div>`;

  return `<div class="clients-page">
    <section class="panel clients-panel">
      <div class="panel-body customer_page_btns clients-panel-body">
        <div class="clients-top-actions">
          <button href="#addClient" data-toggle="modal" type="button" class="btn btn-success add-modal m-bot-10"><img src="assets/img/icons/plusIcon.svg" alt=""> Ավելացնել նոր հաճախորդ</button>
          <button class="btn btn-info m-bot-10" id="emailButton" type="button">Ուղարկել email</button>
          <button class="btn btn-warning m-bot-10" id="smsButton" type="button">Ուղարկել հաղորդագրություն</button>
          <button class="btn btn-default m-bot-10" id="checkAll" type="button">Բոլորը</button>
        </div>
        <div class="clients-toolbar">
          <div class="clients-page-size"><label>Ցույց տալ <select class="form-control input-sm"><option selected>10</option><option>25</option><option>50</option></select> գրառում</label></div>
          <a class="dt-button buttons-excel buttons-html5" tabindex="0"><span><button class="btn btn-primary excelButton" type="button"><img src="assets/img/icons/ExcelLogo.svg" alt=""> Excel</button></span></a>
        </div>
        <div class="clients-table-wrap">
          <table class="table table-striped table-bordered" id="companyGridTable">
            <thead>
              <tr><th>Նշել</th><th>Անուն</th><th>Հասցե</th><th>Հեռախոսահամար</th><th>Էլ․ Հասցե</th><th>Ընկերություն</th><th>Մասնագիտւթյուն</th><th>Ծննդյան ամսաթիվ</th><th>Անձը հաստատող փաստաթուղթ</th><th>Սեռ</th><th class="cogs"><i class="fa fa-cogs" aria-hidden="true"></i></th></tr>
              <tr class="filters"><td></td>${Array.from({ length: 9 }).map(() => '<td><input type="text" class="form-control" placeholder=""></td>').join('')}<td></td></tr>
            </thead>
            <tbody>${rows}</tbody>
          </table>
        </div>
      </div>
    </section>
    <div id="addClient" class="modal fade form-horizontal editModal clients-form-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Ավելացնել</h4></div><div class="modal-body">${clientFields()}</div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-warning pull-right add-client-submit" type="button">Հաստատել</button></div></div></div></div>
    <div id="editClient" class="modal fade form-horizontal editModal clients-form-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Փոփոխել</h4></div><div class="modal-body">${clientFields({ name: 'Անի Մկրտչյան', phone: '091223344', address: 'Երևան, Կենտրոն', email: 'ani@example.com', profession: 'Մենեջեր', birthday: '1994-07-10', doc: 'AN123456', hvhh: '01234567', sex: 'Իգական', percent: '5' })}</div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" id="data-dismiss" data-dismiss="modal">Փակել</button><button class="btn btn-warning pull-right edit-client-submit" type="button">Հաստատել</button></div></div></div></div>
    <div id="deleteClient" class="modal fade deleteModal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h4 class="modal-title">Ջնջել հաճախորդին</h4></div><div class="modal-body"><p>Դուք համոզվա՞ծ եք</p></div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-danger pull-right delete-client-submit" type="button">Հաստատել</button></div></div></div></div>
    <div id="smsModal" class="modal fade" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button data-dismiss="modal" class="close" type="button">×</button><h4 class="modal-title">SMS</h4></div><div class="modal-body"><form><div class="input-group"><select class="form-control" id="sms_templates_select" name="sms_templates"><option value=""></option><option value="Հարգելի հաճախորդ, շնորհակալություն այցելության համար։">Շնորհակալություն</option><option value="Այսօր գործում է հատուկ առաջարկ։">Հատուկ առաջարկ</option></select><span class="input-group-addon pinTemplate"><i class="icon-plus"></i></span></div><div class="form-group"><label>Հաղորդագրություն</label><textarea class="form-control message-textarea" name="message"></textarea></div><input type="hidden" id="clientIds"></form></div><div class="modal-footer"><button class="btn btn-success" id="sendSms">Հաստատել</button></div></div></div></div>
    <div id="emailModal" class="modal fade" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button data-dismiss="modal" class="close" type="button">×</button><h4 class="modal-title">Email</h4></div><div class="modal-body"><form><div class="form-group"><label>Թեմա</label><input type="text" class="form-control subject-email" name="subject-email"></div><div class="form-group"><label>Հաղորդագրություն</label><textarea class="form-control email-textarea" name="email_message"></textarea></div><input type="hidden" id="clientIdsForEmail"></form></div><div class="modal-footer"><button class="btn btn-success" id="sendEmail">Հաստատել</button></div></div></div></div>
  </div>`;
}

function clientsCardsPageContent() {
  const cards = [
    ['2026-07-09 23:10:13', '1234', '???', '', '10', '5200'],
    ['2025-02-18 16:55:35', '1596', 'Կորյուն', '093665544', '10', '14032'],
  ];
  const rows = cards.map(card => `<tr>
      <td>${card[0]}</td>
      <td>${card[1]}</td>
      <td>${card[2]}</td>
      <td>${card[3]}</td>
      <td>${card[4]}</td>
      <td>${card[5]} <span class="card-help">?</span></td>
      <td class="cog_btns_td"><div class="flexBtns">
        <button class="btn btn-success btn-xs inTableIconButton" type="button"><i class="fa fa-money"></i></button>
        <button class="btn btn-default btn-xs inTableIconButton" type="button"><i class="fa fa-minus"></i></button>
        <a href="#editClientCardModal" data-toggle="modal" class="btn btn-warning btn-xs inTableIconButton"><img src="assets/img/icons/pencil.svg" alt=""></a>
        <a href="#deleteClientCardModal" data-toggle="modal" class="btn btn-danger btn-xs inTableIconButton"><img src="assets/img/icons/trash.svg" alt=""></a>
      </div></td>
    </tr>`).join('');
  const cardFields = (values = {}) => `<div class="form-group"><label class="control-label col-sm-4">Քարտի համար:</label><div class="col-sm-8"><input type="text" class="form-control card-number" name="card_number"${values.number ? ` value="${values.number}"` : ''}></div></div>
    <div class="form-group"><label class="control-label col-sm-4">Հաճախորդ:</label><div class="col-sm-8"><select class="form-control client-select" name="client"><option${values.client === 'Անի Մկրտչյան' ? ' selected' : ''}>Անի Մկրտչյան</option><option${values.client === 'Գոռ Սարգսյան' ? ' selected' : ''}>Գոռ Սարգսյան</option><option${values.client === 'Լիլիթ Հարությունյան' ? ' selected' : ''}>Լիլիթ Հարությունյան</option><option${values.client === 'Արամ Պետրոսյան' ? ' selected' : ''}>Արամ Պետրոսյան</option></select></div></div>
    <div class="form-group"><label class="control-label col-sm-4">Կուտակման տոկոս:</label><div class="col-sm-8"><input type="number" class="form-control card-percent" name="percent"${values.percent ? ` value="${values.percent}"` : ''}></div></div>
    <div class="form-group"><label class="control-label col-sm-4">Մնացորդ:</label><div class="col-sm-8"><input type="number" class="form-control card-balance" name="balance"${values.balance ? ` value="${values.balance}"` : ''}></div></div>`;

  return `<div class="clients-cards-page">
    <section class="panel clients-cards-panel">
      <div class="panel-body clients-cards-body">
        <ul class="nav nav-tabs menuButtonsContainer clients-cards-tabs">
          <li class="active"><a href="clients-cards-page.html">Բոնուս</a></li>
          <li><a href="#">Զեղչ</a></li>
        </ul>
        <div class="clients-cards-top-actions">
          <button href="#addClientCardModal" data-toggle="modal" type="button" class="btn btn-success add-card-btn"><img src="assets/img/icons/plusIcon.svg" alt=""> Ավելացնել քարտ</button>
        </div>
        <div class="clients-cards-toolbar">
          <div class="clients-cards-page-size"><label><select class="form-control input-sm"><option selected>10</option><option>25</option><option>50</option></select> Ցուցադրված են 1-ից 2-ը ընդհանուր 2-ից:</label></div>
          <a class="dt-button buttons-excel buttons-html5" tabindex="0"><span><button class="btn btn-primary excelButton" type="button"><img src="assets/img/icons/ExcelLogo.svg" alt=""> Excel</button></span></a>
        </div>
        <div class="clients-cards-table-wrap">
          <table class="table table-striped table-bordered" id="clientCardsGridTable">
            <thead>
              <tr><th>Ամսաթիվ</th><th>Քարտի ID</th><th>Հաճախորդ</th><th>Հեռախոսահամար</th><th>Կուտակման տոկոս</th><th>Մնացորդ</th><th class="cogs"><i class="fa fa-cogs" aria-hidden="true"></i></th></tr>
              <tr class="filters">${Array.from({ length: 6 }).map(() => '<td><input type="text" class="form-control" placeholder=""></td>').join('')}<td></td></tr>
            </thead>
            <tbody>${rows}</tbody>
          </table>
        </div>
      </div>
    </section>
    <div id="addClientCardModal" class="modal fade form-horizontal clients-card-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Ավելացնել քարտ</h4></div><div class="modal-body">${cardFields()}</div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-warning pull-right" type="button">Հաստատել</button></div></div></div></div>
    <div id="editClientCardModal" class="modal fade form-horizontal clients-card-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Փոփոխել քարտը</h4></div><div class="modal-body">${cardFields({ number: '1234', client: 'Անի Մկրտչյան', percent: '10', balance: '5200' })}</div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-warning pull-right" type="button">Հաստատել</button></div></div></div></div>
    <div id="deleteClientCardModal" class="modal fade deleteModal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h4 class="modal-title">Ջնջել քարտը</h4></div><div class="modal-body"><p>Դուք համոզվա՞ծ եք</p></div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-danger pull-right" type="button">Հաստատել</button></div></div></div></div>
  </div>`;
}

function clientsCardsHistoryContent() {
  const rows = [
    ['2026-07-09 23:10:13', '1234', '???', '', '10', '5200', 'Կուտակում'],
    ['2026-07-09 22:48:31', '1234', '???', '', '10', '-1800', 'Օգտագործում'],
    ['2025-02-18 16:55:35', '1596', 'Կորյուն', '093665544', '10', '14032', 'Կուտակում'],
    ['2025-02-18 16:21:10', '1596', 'Կորյուն', '093665544', '10', '0', 'Ստուգում'],
  ];
  const tableRows = rows.map(row => `<tr>
      <td>${row[0]}</td>
      <td>${row[1]}</td>
      <td>${row[2]}</td>
      <td>${row[3]}</td>
      <td>${row[4]}</td>
      <td>${row[5]}</td>
      <td>${row[6]}</td>
    </tr>`).join('');

  return `<div class="clients-cards-history-page">
    <section class="panel clients-cards-history-panel">
      <div class="panel-body clients-cards-history-body">
        <ul class="nav nav-tabs menuButtonsContainer clients-cards-history-tabs">
          <li class="active"><a href="clients-cards.html">Բոնուս</a></li>
          <li><a href="#">Զեղչ</a></li>
        </ul>
        <div class="clients-cards-history-toolbar">
          <div class="clients-cards-history-page-size"><label><select class="form-control input-sm"><option selected>10</option><option>25</option><option>50</option></select> Ցուցադրված են 1-ից 4-ը ընդհանուր 4-ից:</label></div>
          <a class="dt-button buttons-excel buttons-html5" tabindex="0"><span><button class="btn btn-primary excelButton" type="button"><img src="assets/img/icons/ExcelLogo.svg" alt=""> Excel</button></span></a>
        </div>
        <div class="clients-cards-history-table-wrap">
          <table class="table table-striped table-bordered" id="clientCardsHistoryGridTable">
            <thead>
              <tr><th>Ամսաթիվ</th><th>Քարտի ID</th><th>Հաճախորդ</th><th>Հեռախոսահամար</th><th>Կուտակման տոկոս</th><th>Գումար</th><th>Գործողություն</th></tr>
              <tr class="filters">${Array.from({ length: 7 }).map(() => '<td><input type="text" class="form-control" placeholder=""></td>').join('')}</tr>
            </thead>
            <tbody>${tableRows}</tbody>
          </table>
        </div>
      </div>
    </section>
  </div>`;
}

function clientsResponseSettingsContent() {
  const types = [
    ['1', 'Շնորհակալություն', 'Դրական արձագանք', true],
    ['2', 'Բողոք', 'Բացասական արձագանք', true],
    ['3', 'Առաջարկ', 'Հաճախորդի առաջարկ', true],
    ['4', 'Սպասարկում', 'Սպասարկման որակ', false],
  ];
  const rows = types.map(row => `<tr>
      <td>${row[0]}</td>
      <td>${row[1]}</td>
      <td>${row[2]}</td>
      <td>${row[3] ? 'Ակտիվ' : 'Պասիվ'}</td>
      <td class="cog_btns_td"><div class="flexBtns">
        <a href="#editResponseTypeModal" data-toggle="modal" class="btn btn-warning btn-xs inTableIconButton"><img src="assets/img/icons/pencil.svg" alt=""></a>
        <a href="#deleteResponseTypeModal" data-toggle="modal" class="btn btn-danger btn-xs inTableIconButton"><img src="assets/img/icons/trash.svg" alt=""></a>
      </div></td>
    </tr>`).join('');
  const fields = (values = {}) => `<div class="form-group"><label class="control-label col-sm-4">Անվանում:</label><div class="col-sm-8"><input type="text" class="form-control response-name" name="name"${values.name ? ` value="${values.name}"` : ''}></div></div>
    <div class="form-group"><label class="control-label col-sm-4">Նկարագրություն:</label><div class="col-sm-8"><input type="text" class="form-control response-description" name="description"${values.description ? ` value="${values.description}"` : ''}></div></div>
    <div class="form-group"><label class="control-label col-sm-4">Կարգավիճակ:</label><div class="col-sm-8"><select class="form-control response-status" name="status"><option${values.status !== 'Պասիվ' ? ' selected' : ''}>Ակտիվ</option><option${values.status === 'Պասիվ' ? ' selected' : ''}>Պասիվ</option></select></div></div>`;

  return `<div class="clients-response-settings-page">
    <section class="panel clients-response-settings-panel">
      <div class="panel-body clients-response-settings-body">
        <div class="clients-response-settings-top-actions">
          <button href="#addResponseTypeModal" data-toggle="modal" type="button" class="btn btn-success add-response-type-btn"><img src="assets/img/icons/plusIcon.svg" alt=""> Ավելացնել</button>
        </div>
        <div class="clients-response-settings-toolbar">
          <div class="clients-response-settings-page-size"><label>Ցույց տալ <select class="form-control input-sm"><option selected>10</option><option>25</option><option>50</option></select> գրառում</label></div>
          <a class="dt-button buttons-excel buttons-html5" tabindex="0"><span><button class="btn btn-primary excelButton" type="button"><img src="assets/img/icons/ExcelLogo.svg" alt=""> Excel</button></span></a>
        </div>
        <div class="clients-response-settings-table-wrap">
          <table class="table table-striped table-bordered" id="clientResponseSettingsGridTable">
            <thead>
              <tr><th>ID</th><th>Անվանում</th><th>Նկարագրություն</th><th>Կարգավիճակ</th><th class="cogs"><i class="fa fa-cogs" aria-hidden="true"></i></th></tr>
              <tr class="filters">${Array.from({ length: 4 }).map(() => '<td><input type="text" class="form-control" placeholder=""></td>').join('')}<td></td></tr>
            </thead>
            <tbody>${rows}</tbody>
          </table>
        </div>
      </div>
    </section>
    <div id="addResponseTypeModal" class="modal fade form-horizontal clients-response-settings-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Ավելացնել արձագանքի տեսակ</h4></div><div class="modal-body">${fields()}</div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-warning pull-right" type="button">Հաստատել</button></div></div></div></div>
    <div id="editResponseTypeModal" class="modal fade form-horizontal clients-response-settings-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Փոփոխել արձագանքի տեսակը</h4></div><div class="modal-body">${fields({ name: 'Շնորհակալություն', description: 'Դրական արձագանք', status: 'Ակտիվ' })}</div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-warning pull-right" type="button">Հաստատել</button></div></div></div></div>
    <div id="deleteResponseTypeModal" class="modal fade deleteModal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h4 class="modal-title">Ջնջել արձագանքի տեսակը</h4></div><div class="modal-body"><p>Դուք համոզվա՞ծ եք</p></div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-danger pull-right" type="button">Հաստատել</button></div></div></div></div>
  </div>`;
}

function clientsResponseContent() {
  const responses = [
    ['2026-07-10 14:20', 'Անի Մկրտչյան', '091 22 33 44', 'Շնորհակալություն', 'Սպասարկումը շատ լավ էր', 'Նոր'],
    ['2026-07-10 12:45', 'Գոռ Սարգսյան', '093 45 67 89', 'Բողոք', 'Պատվերը ուշացավ', 'Դիտված'],
    ['2026-07-09 19:10', 'Լիլիթ Հարությունյան', '077 11 22 33', 'Առաջարկ', 'Ավելացնել մանկական մենյու', 'Նոր'],
    ['2026-07-08 21:35', 'Արամ Պետրոսյան', '099 80 70 60', 'Սպասարկում', 'Մատուցողը արագ սպասարկեց', 'Դիտված'],
  ];
  const rows = responses.map(row => `<tr>
      <td>${row[0]}</td>
      <td><a class="text-primary" href="clients.html">${row[1]}</a></td>
      <td>${row[2]}</td>
      <td>${row[3]}</td>
      <td>${row[4]}</td>
      <td>${row[5]}</td>
      <td class="cog_btns_td"><div class="flexBtns">
        <a href="#viewClientResponseModal" data-toggle="modal" class="btn btn-info btn-xs inTableIconButton"><i class="fa fa-eye"></i></a>
        <a href="#deleteClientResponseModal" data-toggle="modal" class="btn btn-danger btn-xs inTableIconButton"><img src="assets/img/icons/trash.svg" alt=""></a>
      </div></td>
    </tr>`).join('');

  return `<div class="clients-response-page">
    <section class="panel clients-response-panel">
      <div class="panel-body clients-response-body">
        <form method="get" class="clients-response-filters">
          <button type="button" class="btn btn-success" name="filter" value="new">Նոր</button>
          <button type="button" class="btn btn-warning" name="filter" value="seen">Դիտված</button>
          <button type="button" class="btn btn-default" name="filter" value="all">Բոլորը</button>
        </form>
        <div class="clients-response-toolbar">
          <div class="clients-response-page-size"><label>Ցույց տալ <select class="form-control input-sm"><option selected>10</option><option>25</option><option>50</option></select> գրառում</label></div>
          <a class="dt-button buttons-excel buttons-html5" tabindex="0"><span><button class="btn btn-primary excelButton" type="button"><img src="assets/img/icons/ExcelLogo.svg" alt=""> Excel</button></span></a>
        </div>
        <div class="clients-response-table-wrap">
          <table class="table table-striped table-bordered" id="clientResponseGridTable">
            <thead>
              <tr><th>Ամսաթիվ</th><th>Հաճախորդ</th><th>Հեռախոսահամար</th><th>Տեսակ</th><th>Հաղորդագրություն</th><th>Կարգավիճակ</th><th class="cogs"><i class="fa fa-cogs" aria-hidden="true"></i></th></tr>
              <tr class="filters">${Array.from({ length: 6 }).map(() => '<td><input type="text" class="form-control" placeholder=""></td>').join('')}<td></td></tr>
            </thead>
            <tbody>${rows}</tbody>
          </table>
        </div>
      </div>
    </section>
    <div id="viewClientResponseModal" class="modal fade clients-response-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Հաճախորդի արձագանք</h4></div><div class="modal-body"><div class="form-group"><label>Հաճախորդ</label><input class="form-control" value="Անի Մկրտչյան" readonly></div><div class="form-group"><label>Տեսակ</label><input class="form-control" value="Շնորհակալություն" readonly></div><div class="form-group"><label>Հաղորդագրություն</label><textarea class="form-control response-message-textarea" readonly>Սպասարկումը շատ լավ էր</textarea></div></div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-warning pull-right" type="button">Նշել դիտված</button></div></div></div></div>
    <div id="deleteClientResponseModal" class="modal fade deleteModal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h4 class="modal-title">Ջնջել արձագանքը</h4></div><div class="modal-body"><p>Դուք համոզվա՞ծ եք</p></div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-danger pull-right" type="button">Հաստատել</button></div></div></div></div>
  </div>`;
}

function clientsUnservedContent() {
  const rows = [
    ['2026-07-10 15:05', 'Անի Մկրտչյան', '091 22 33 44', 'Հեռախոսազանգ', 'Չպատասխանեց', 'Նոր'],
    ['2026-07-10 13:40', 'Գոռ Սարգսյան', '093 45 67 89', 'Կայք', 'Սեղան չկար', 'Դիտված'],
    ['2026-07-09 20:15', 'Լիլիթ Հարությունյան', '077 11 22 33', 'Առաքում', 'Տարածքից դուրս', 'Նոր'],
    ['2026-07-09 18:30', 'Արամ Պետրոսյան', '099 80 70 60', 'Հեռախոսազանգ', 'Պատվերը չհաստատվեց', 'Փակված'],
  ];
  const tableRows = rows.map(row => `<tr>
      <td>${row[0]}</td>
      <td><a class="text-primary" href="clients.html">${row[1]}</a></td>
      <td>${row[2]}</td>
      <td>${row[3]}</td>
      <td>${row[4]}</td>
      <td>${row[5]}</td>
      <td class="cog_btns_td"><div class="flexBtns">
        <a href="#viewUnservedClientModal" data-toggle="modal" class="btn btn-info btn-xs inTableIconButton"><i class="fa fa-eye"></i></a>
        <a href="#closeUnservedClientModal" data-toggle="modal" class="btn btn-warning btn-xs inTableIconButton"><i class="fa fa-check"></i></a>
        <a href="#deleteUnservedClientModal" data-toggle="modal" class="btn btn-danger btn-xs inTableIconButton"><img src="assets/img/icons/trash.svg" alt=""></a>
      </div></td>
    </tr>`).join('');

  return `<div class="clients-unserved-page">
    <section class="panel clients-unserved-panel">
      <div class="panel-body clients-unserved-body">
        <form method="get" class="clients-unserved-filters">
          <button type="button" class="btn btn-success" name="filter" value="new">Նոր</button>
          <button type="button" class="btn btn-warning" name="filter" value="seen">Դիտված</button>
          <button type="button" class="btn btn-default" name="filter" value="all">Բոլորը</button>
        </form>
        <div class="clients-unserved-toolbar">
          <div class="clients-unserved-page-size"><label>Ցույց տալ <select class="form-control input-sm"><option selected>10</option><option>25</option><option>50</option></select> գրառում</label></div>
          <a class="dt-button buttons-excel buttons-html5" tabindex="0"><span><button class="btn btn-primary excelButton" type="button"><img src="assets/img/icons/ExcelLogo.svg" alt=""> Excel</button></span></a>
        </div>
        <div class="clients-unserved-table-wrap">
          <table class="table table-striped table-bordered" id="clientUnservedGridTable">
            <thead>
              <tr><th>Ամսաթիվ</th><th>Հաճախորդ</th><th>Հեռախոսահամար</th><th>Աղբյուր</th><th>Պատճառ</th><th>Կարգավիճակ</th><th class="cogs"><i class="fa fa-cogs" aria-hidden="true"></i></th></tr>
              <tr class="filters">${Array.from({ length: 6 }).map(() => '<td><input type="text" class="form-control" placeholder=""></td>').join('')}<td></td></tr>
            </thead>
            <tbody>${tableRows}</tbody>
          </table>
        </div>
      </div>
    </section>
    <div id="viewUnservedClientModal" class="modal fade clients-unserved-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Չսպասարկված հաճախորդ</h4></div><div class="modal-body"><div class="form-group"><label>Հաճախորդ</label><input class="form-control" value="Անի Մկրտչյան" readonly></div><div class="form-group"><label>Հեռախոսահամար</label><input class="form-control" value="091 22 33 44" readonly></div><div class="form-group"><label>Պատճառ</label><textarea class="form-control unserved-note-textarea" readonly>Չպատասխանեց</textarea></div></div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-warning pull-right" type="button">Նշել դիտված</button></div></div></div></div>
    <div id="closeUnservedClientModal" class="modal fade clients-unserved-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h4 class="modal-title">Փակել գրառումը</h4></div><div class="modal-body"><p>Նշե՞լ այս գրառումը որպես փակված։</p></div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-warning pull-right" type="button">Հաստատել</button></div></div></div></div>
    <div id="deleteUnservedClientModal" class="modal fade deleteModal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h4 class="modal-title">Ջնջել գրառումը</h4></div><div class="modal-body"><p>Դուք համոզվա՞ծ եք</p></div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-danger pull-right" type="button">Հաստատել</button></div></div></div></div>
  </div>`;
}

function clientsComplaintsContent() {
  const rows = [
    ['2026-07-10 12:45', 'Գոռ Սարգսյան', '093 45 67 89', 'Պատվերի ուշացում', 'Պատվերը ուշացավ 35 րոպե', 'Նոր'],
    ['2026-07-09 21:20', 'Անի Մկրտչյան', '091 22 33 44', 'Սպասարկում', 'Մատուցողը ուշ մոտեցավ', 'Ընթացքում'],
    ['2026-07-08 18:10', 'Լիլիթ Հարությունյան', '077 11 22 33', 'Որակ', 'Աղցանը սառը չէր', 'Լուծված'],
    ['2026-07-07 20:55', 'Արամ Պետրոսյան', '099 80 70 60', 'Հաշիվ', 'Հաշվում սխալ գումար կար', 'Լուծված'],
  ];
  const tableRows = rows.map(row => `<tr>
      <td>${row[0]}</td>
      <td><a class="text-primary" href="clients.html">${row[1]}</a></td>
      <td>${row[2]}</td>
      <td>${row[3]}</td>
      <td>${row[4]}</td>
      <td>${row[5]}</td>
      <td class="cog_btns_td"><div class="flexBtns">
        <a href="#viewClientComplaintModal" data-toggle="modal" class="btn btn-info btn-xs inTableIconButton"><i class="fa fa-eye"></i></a>
        <a href="#resolveClientComplaintModal" data-toggle="modal" class="btn btn-warning btn-xs inTableIconButton"><i class="fa fa-check"></i></a>
        <a href="#deleteClientComplaintModal" data-toggle="modal" class="btn btn-danger btn-xs inTableIconButton"><img src="assets/img/icons/trash.svg" alt=""></a>
      </div></td>
    </tr>`).join('');

  return `<div class="clients-complaints-page">
    <section class="panel clients-complaints-panel">
      <div class="panel-body clients-complaints-body">
        <form method="get" class="clients-complaints-filters">
          <button type="button" class="btn btn-success" name="filter" value="new">Նոր</button>
          <button type="button" class="btn btn-warning" name="filter" value="progress">Ընթացքում</button>
          <button type="button" class="btn btn-default" name="filter" value="all">Բոլորը</button>
        </form>
        <div class="clients-complaints-toolbar">
          <div class="clients-complaints-page-size"><label>Ցույց տալ <select class="form-control input-sm"><option selected>10</option><option>25</option><option>50</option></select> գրառում</label></div>
          <a class="dt-button buttons-excel buttons-html5" tabindex="0"><span><button class="btn btn-primary excelButton" type="button"><img src="assets/img/icons/ExcelLogo.svg" alt=""> Excel</button></span></a>
        </div>
        <div class="clients-complaints-table-wrap">
          <table class="table table-striped table-bordered" id="clientComplaintsGridTable">
            <thead>
              <tr><th>Ամսաթիվ</th><th>Հաճախորդ</th><th>Հեռախոսահամար</th><th>Թեմա</th><th>Բողոք</th><th>Կարգավիճակ</th><th class="cogs"><i class="fa fa-cogs" aria-hidden="true"></i></th></tr>
              <tr class="filters">${Array.from({ length: 6 }).map(() => '<td><input type="text" class="form-control" placeholder=""></td>').join('')}<td></td></tr>
            </thead>
            <tbody>${tableRows}</tbody>
          </table>
        </div>
      </div>
    </section>
    <div id="viewClientComplaintModal" class="modal fade clients-complaints-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Հաճախորդի բողոք</h4></div><div class="modal-body"><div class="form-group"><label>Հաճախորդ</label><input class="form-control" value="Գոռ Սարգսյան" readonly></div><div class="form-group"><label>Թեմա</label><input class="form-control" value="Պատվերի ուշացում" readonly></div><div class="form-group"><label>Բողոք</label><textarea class="form-control complaint-textarea" readonly>Պատվերը ուշացավ 35 րոպե</textarea></div></div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-warning pull-right" type="button">Նշել ընթացքի մեջ</button></div></div></div></div>
    <div id="resolveClientComplaintModal" class="modal fade clients-complaints-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h4 class="modal-title">Լուծել բողոքը</h4></div><div class="modal-body"><p>Նշե՞լ այս բողոքը որպես լուծված։</p><textarea class="form-control complaint-textarea" placeholder="Մեկնաբանություն"></textarea></div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-warning pull-right" type="button">Հաստատել</button></div></div></div></div>
    <div id="deleteClientComplaintModal" class="modal fade deleteModal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h4 class="modal-title">Ջնջել բողոքը</h4></div><div class="modal-body"><p>Դուք համոզվա՞ծ եք</p></div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-danger pull-right" type="button">Հաստատել</button></div></div></div></div>
  </div>`;
}

function staffContent() {
  const staff = [
    { id: '1', firstName: 'Արամ', lastName: 'Սարգսյան', position: 'Մատուցող(ուհի)', mobTel: '091 11 22 33', birth: '1992-04-12', dismission: '', photo: 'profile-avatar.jpg' },
    { id: '2', firstName: 'Նարե', lastName: 'Մկրտչյան', position: 'Գանձապահ', mobTel: '093 44 55 66', birth: '1995-09-21', dismission: '', photo: 'profile-avatar.jpg' },
    { id: '3', firstName: 'Գոռ', lastName: 'Մարտիրոսյան', position: 'Խոհարար', mobTel: '077 77 88 99', birth: '1988-02-03', dismission: '', photo: 'profile-avatar.jpg' },
    { id: '4', firstName: 'Լիլիթ', lastName: 'Հարությունյան', position: 'Մենեջեր', mobTel: '099 12 34 56', birth: '1990-12-18', dismission: '2026-06-01', photo: 'profile-avatar.jpg' },
  ];
  const positions = ['Մատուցող(ուհի)', 'Մատուցողի օգնական', 'Բարմեն', 'Բարմենի օգնական', 'Խոհարար', 'Խոհարարի օգնական', 'Գլխավոր խոհարար', 'Լվացարար', 'Հավաքարար', 'Տնտեսվար', 'Առաքիչ', 'Վարորդ', 'Անվտանգություն', 'Պահեստապետ', 'Հաշվապահ', 'Մենեջեր', 'Ադմինիստրատոր', 'Տնօրեն', 'Այլ'];
  const menuPlaces = ['Հիմնական սրահ', 'Ամառային սրահ', 'Բար'];
  const selected = (value, selectedValue) => value === selectedValue ? ' selected' : '';
  const valueAttr = value => value ? ` value="${value}"` : '';
  const inputGroup = (name, suffix = '%', value = '') => `<div class="input-group m-bot15"><input type="text" class="form-control numberic" name="${name}"${valueAttr(value)}><span class="input-group-addon">${suffix}</span></div>`;
  const monthDayOptions = (selectedValue = '1') => `${Array.from({ length: 28 }).map((_, i) => `<option value="${i + 1}"${selected(String(i + 1), selectedValue)}>${i + 1}</option>`).join('')}<option value="end_of_month"${selected('end_of_month', selectedValue)}>Ամսվա վերջ</option>`;
  const rows = staff.map(row => `<tr>
      <td>${row.firstName}</td>
      <td>${row.lastName}</td>
      <td>${row.position}</td>
      <td>${row.mobTel}</td>
      <td>${row.birth}</td>
      <td>${row.dismission}</td>
      <td><img src="assets/img/common/avatar-mini.jpg" class="staff-photo-thumb" alt=""></td>
      <td class="cog_btns_td"><div class="flexBtns">
        <a href="#editStaffModal" data-toggle="modal" class="btn btn-warning btn-xs inTableIconButton"><img src="assets/img/icons/pencil.svg" alt=""></a>
        <a href="#deleteStaffModal" data-toggle="modal" class="btn btn-danger btn-xs inTableIconButton"><img src="assets/img/icons/trash.svg" alt=""></a>
        <a href="#viewStaffModal" data-toggle="modal" class="btn btn-info btn-xs inTableIconButton"><i class="fa fa-info"></i></a>
      </div></td>
    </tr>`).join('');
  const fields = (values = {}, actionLabel = 'Ավելացնել') => `<form class="form-horizontal staff-form" autocomplete="off">
    <div class="stepy-tab"><ul class="stepy-titles clearfix"><li class="current-step"><div>Քայլ 1</div></li><li><div>Քայլ 2</div></li></ul></div>
    <fieldset class="staff-fieldset step staff-step-current"><legend></legend>
      <div class="form-group"><label class="control-label col-sm-4">Անուն: *</label><div class="col-sm-8"><input type="text" class="form-control staff-first-name" name="firstname"${valueAttr(values.firstName)}></div></div>
      <div class="form-group"><label class="control-label col-sm-4">Ազգանուն: *</label><div class="col-sm-8"><input type="text" class="form-control staff-last-name" name="surname"${valueAttr(values.lastName)}></div></div>
      <div class="form-group"><label class="control-label col-sm-4">Քարտի ID:</label><div class="col-sm-8"><input type="password" class="form-control staff-card" name="card_key"${valueAttr(values.card)}></div></div>
      <div class="form-group"><label class="control-label col-sm-4">Էլ. փոստ:</label><div class="col-sm-8"><input type="email" class="form-control staff-email" name="mail"${valueAttr(values.email)}></div></div>
      <div class="form-group"><label class="control-label col-sm-4">Հեռախոսահամար:</label><div class="col-sm-8"><input type="text" class="form-control numberic staff-phone" name="home_tel"${valueAttr(values.homeTel)}></div></div>
      <div class="form-group"><label class="control-label col-sm-4">Բջջային համար:</label><div class="col-sm-8"><input type="text" class="form-control numberic staff-mobile" name="mob_tel"${valueAttr(values.mobTel)}></div></div>
      <div class="form-group"><label class="control-label col-sm-4">Հասցե:</label><div class="col-sm-8"><textarea class="form-control staff-address" cols="60" rows="3" name="address">${values.address || ''}</textarea></div></div>
      <div class="form-group"><label class="control-label col-sm-4">Ծննդյան օր:</label><div class="col-sm-8"><input type="date" class="form-control date_of_birth" name="date_of_birth"${valueAttr(values.birth)}></div></div>
      <div class="form-group"><label class="control-label col-sm-4">Ազատման ամսաթիվ:</label><div class="col-sm-8"><input type="date" class="form-control dismission_date" name="dismission_date"${valueAttr(values.dismission)}></div></div>
      <div class="form-group"><label class="control-label col-sm-4">Նկար:</label><div class="col-sm-8 hint_for_picture"><input type="file" class="default staff-photo" name="photo"><input type="hidden" class="data photo photo_uploaded" name="photo_uploaded" value="${values.photo || ''}"><div class="photo_uploaded_complete"></div></div></div>
    </fieldset>
    <fieldset class="staff-fieldset step staff-step-hidden"><legend></legend>
      <div class="form-group"><label class="control-label col-sm-4">Հաստիք: *</label><div class="col-sm-8"><select class="form-control staff-position" name="position"><option value=""></option>${positions.map(position => `<option value="${position}"${selected(position, values.position)}>${position}</option>`).join('')}</select></div></div>
      <div class="form-group"><label class="control-label col-sm-4">Օրական հաստատագրված:</label><div class="col-sm-8"><input type="text" class="form-control numberic" name="fix_day"${valueAttr(values.fixDay)}></div></div>
      <div class="form-group"><label class="control-label col-sm-4">Ժամային հաստատագրված:</label><div class="col-sm-8"><input type="text" class="form-control numberic" name="fix_hour"${valueAttr(values.fixHour)}></div></div>
      <div class="form-group"><label class="control-label col-sm-4">Ամսական հաստատագրված:</label><div class="col-sm-8"><input type="text" class="form-control numberic" name="fix_month"${valueAttr(values.fixMonth)}></div></div>
      <div class="form-group"><label class="control-label col-sm-4">Ամսական աշխատավարձի օր:</label><div class="col-sm-8"><select name="month_salary_day" class="form-control">${monthDayOptions(values.monthSalaryDay)}</select></div></div>
      <div class="form-group"><label class="control-label col-sm-4">Ժամային հաշիվ:</label><div class="col-sm-8">${inputGroup('total_summ_hour', '%', values.totalSummHour)}<p class="text-left staff-help"><i class="icon-info-sign"></i> Մուտքագրեք աշխատակցի ներկայության ընթացքում փակված հաշիվների գումարի համապատասխան ստանալիք տոկոսը։</p></div></div>
      <div class="form-group"><label class="control-label col-sm-4">Կցել մենյու:</label><div class="col-sm-8"><select name="attached_menu" class="form-control multi-select numberic" multiple="multiple">${menuPlaces.map(place => `<option${(values.attachedMenu || []).includes(place) ? ' selected' : ''}>${place}</option>`).join('')}</select></div></div>
      <div class="form-group"><label class="control-label col-sm-4">Փակված հաշիվներից տոկոսավճար:</label><div class="col-sm-8">${inputGroup('menu_percent', '%', values.menuPercent)}<p class="text-left staff-help"><i class="icon-info-sign"></i> Մուտքագրեք աշխատակցի ներկայության ընթացքում իրեն կցված մենյուների փակված հաշիվների գումարի համապատասխան ստանալիք տոկոսը։</p></div></div>
      <div class="form-group"><label class="control-label col-sm-4">Անձի առևտուր:</label><div class="col-sm-8">${inputGroup('total_summ_person', '%', values.totalSummPerson)}<p class="text-left staff-help"><i class="icon-info-sign"></i> Մուտքագրեք աշխատակցին կցված սեղանների փակված հաշիվների գումարի համապատասխան ստանալիք տոկոսը։</p></div></div>
      <div class="form-group"><label class="control-label col-sm-4">Օրական տոկոսավճար:</label><div class="col-sm-8"><div class="input-group m-bot15"><span class="input-group-addon"><input type="checkbox"${values.distribute ? ' checked' : ''}> <span class="daily_payment_text">Բաշխել հավասարաչափ</span></span><input type="text" class="form-control numberic daily_payment_input" name="percent_summ_day"${valueAttr(values.percentSummDay)}><span class="input-group-addon">%</span></div><p class="text-left staff-help"><i class="icon-info-sign"></i> Մուտքագրեք աշխատակցի ներկա գտնվելու օրվա փակված հաշիվների տոկոսավճարների գումարի համապատասխան ստանալիք տոկոսը։</p></div></div>
    </fieldset>
    <div class="staff-wizard-actions clearfix"><button type="button" class="button-back btn btn-default">Հետ</button><button type="button" class="button-next btn btn-info">Առաջ</button><button type="button" class="finish btn btn-success">${actionLabel}</button></div>
  </form>`;

  return `<div class="staff-page">
    <section class="panel staff-panel">
      <div class="panel-body staff-body">
        <div class="staff-top-actions">
          <button href="#addStaffModal" data-toggle="modal" type="button" class="btn btn-success add-staff-btn"><img src="assets/img/icons/plusIcon.svg" alt=""> Ավելացնել աշխատակից</button>
        </div>
        <div class="staff-toolbar">
          <div class="staff-page-size"><label>Ցույց տալ <select class="form-control input-sm"><option selected>10</option><option>25</option><option>50</option></select> գրառում</label></div>
          <a class="dt-button buttons-excel buttons-html5" tabindex="0"><span><button class="btn btn-primary excelButton" type="button"><img src="assets/img/icons/ExcelLogo.svg" alt=""> Excel</button></span></a>
        </div>
        <div class="staff-table-wrap">
          <table class="table table-striped table-bordered" id="staffGridTable">
            <thead>
              <tr><th>Անուն</th><th>Ազգանուն</th><th>Հաստիք</th><th>Բջջային համար</th><th>Ծննդյան օր</th><th>Ազատման ամսաթիվ</th><th>Նկար</th><th class="cogs"><i class="fa fa-cogs" aria-hidden="true"></i></th></tr>
              <tr class="filters">${Array.from({ length: 7 }).map(() => '<td><input type="text" class="form-control" placeholder=""></td>').join('')}<td></td></tr>
            </thead>
            <tbody>${rows}</tbody>
          </table>
        </div>
      </div>
    </section>
    <div id="addStaffModal" class="modal fade staff-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><strong>Ավելացնել նոր աշխատակից</strong></h4></div><div class="modal-body text-center"><div class="panel-body">${fields({}, 'Ավելացնել')}</div></div></div></div></div>
    <div id="editStaffModal" class="modal fade staff-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><strong>Փոփոխել աշխատակցին</strong></h4></div><div class="modal-body text-center"><div class="panel-body">${fields({ firstName: 'Արամ', lastName: 'Սարգսյան', position: 'Մատուցող(ուհի)', homeTel: '011 22 33 44', mobTel: '091 11 22 33', email: 'aram@example.com', card: '1001', address: 'Երևան', birth: '1992-04-12', dismission: '', photo: 'profile-avatar.jpg', fixDay: '8000', fixHour: '1200', fixMonth: '180000', monthSalaryDay: 'end_of_month', totalSummHour: '5', attachedMenu: ['Հիմնական սրահ'], menuPercent: '3', totalSummPerson: '2', percentSummDay: '1', distribute: true }, 'Փոփոխել')}</div></div></div></div></div>
    <div id="deleteStaffModal" class="modal fade staff-delete-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Ջնջել Աշխատակցին</h4></div><div class="modal-body"><form class="form-horizontal text-center"><div class="form-group"><label class="col-lg-12 control-label text-center" style="font-size:20px;">Դուք համոզված ե՞ք ջնջել</label></div></form></div><div class="modal-footer"><button class="btn btn-danger" type="button">Հաստատել</button></div></div></div></div>
    <div id="viewStaffModal" class="modal fade staff-view-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Արամ Սարգսյան</h4></div><div class="modal-body">
      <div class="panel-body bio-graph-info">
        <div class="row">
          <div class="bio-row"><p>Հաստիք: <span>Մատուցող(ուհի)</span></p></div>
          <div class="bio-row"><p>Էլ. փոստ: <span>aram@example.com</span></p></div>
          <div class="bio-row"><p>Հեռախոս: <span>011 22 33 44</span></p></div>
          <div class="bio-row"><p>Բջջային: <span>091 11 22 33</span></p></div>
          <div class="bio-row"><p>Հասցե: <span>Երևան</span></p></div>
          <div class="bio-row"><p>Օրական հաստատագրված: <span>8000</span></p></div>
          <div class="bio-row"><p>Ամսական հաստատագրված: <span>180000</span></p></div>
          <div class="bio-row"><p>Ժամային հաշիվ: <span>5%</span></p></div>
          <div class="bio-row"><p>Օրական տոկոսավճար: <span>1%</span></p></div>
          <div class="bio-row"><p>Պարտք: <span id="total_debt">0</span></p></div>
        </div>
      </div>
      <div class="panel-body bio-graph-info staff-presence-block">
        <h1>Ներկայություն</h1>
        <div class="flat-carousal">
          <table id="calendar2">
            <thead>
              <tr><td><i class="icon-caret-left"></i></td><td colspan="5">Հուլիս 2026</td><td><i class="icon-caret-right"></i></td></tr>
              <tr><td>Երկ</td><td>Երք</td><td>Չոր</td><td>Հն</td><td>Ուր</td><td>Շբ</td><td>Կիր</td></tr>
            </thead>
            <tbody>
              <tr><td></td><td></td><td class="mark">1</td><td class="mark">2</td><td>3</td><td>4</td><td>5</td></tr>
              <tr><td class="mark">6</td><td class="mark">7</td><td>8</td><td class="mark">9</td><td>10</td><td>11</td><td>12</td></tr>
              <tr><td class="mark">13</td><td>14</td><td class="mark">15</td><td class="mark">16</td><td>17</td><td>18</td><td>19</td></tr>
              <tr><td>20</td><td class="mark">21</td><td>22</td><td class="mark">23</td><td>24</td><td>25</td><td>26</td></tr>
              <tr><td>27</td><td>28</td><td>29</td><td>30</td><td>31</td><td></td><td></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div></div></div></div>
  </div>`;
}

function clientsExpensesContent() {
  const rows = [
    ['101', true, '2026-07-10 14:25', '10244', 'Սեղան 4 / Main Hall', 'Անի Մկրտչյան', '24500', '5000', '19500', 'Կիսամարված հաշիվ', false],
    ['102', false, '2026-07-10 12:10', '10239', 'Սեղան 8 / Terrace', 'Անի Մկրտչյան', '18200', '0', '18200', 'Պարտք', false],
    ['103', false, '2026-07-09 21:35', '10210', 'Սեղան 2 / Main Hall', 'Գոռ Սարգսյան', '9600', '9600', '0', 'Մարված', true],
  ];
  const tableRows = rows.map(row => `<tr id="clientDebt${row[0]}">
      <td><input data-id="${row[0]}" data-client="${row[10] ? '2' : '1'}" type="checkbox" class="select-on-check" value="1"${row[1] ? ' checked' : ''}${row[10] ? ' disabled' : ''}></td>
      <td>${row[2]}</td>
      <td><a href="reports-check.html" target="_blank">№ #${row[3]}</a></td>
      <td>${row[4]}</td>
      <td>${row[5]}</td>
      <td>${row[6]}</td>
      <td>${row[7]}</td>
      <td data-balance="${row[8]}">${row[8]}</td>
      <td>${row[9]}</td>
      <td class="cog_btns_td"><div class="flexBtns"><button class="btn btn-xs btn-info pay" value="${row[0]}" data-toggle="modal" data-target="#payModal">Մարել</button></div></td>
    </tr>`).join('');

  return `<div class="clients-expenses-page">
    <section class="panel clients-expenses-panel">
      <header class="panel-heading clients-expenses-heading">
        <ul class="nav nav-tabs menuButtonsContainer">
          <li class="active"><a href="clients-expenses.html">Պարտքեր</a></li>
          <li><a href="#">Պատվերներ</a></li>
          <li><a href="#">Առևտուր</a></li>
          <li><a href="#">Միջնորդավորված վաճառք</a></li>
        </ul>
      </header>
      <div class="panel-body client_debt_table_body clients-expenses-body">
        <div class="clients-expenses-filter-row"><form role="form" method="get"><div class="input-group input-large header_filter" data-date-format="yyyy/mm/dd" id="noPadL"><input type="date" class="form-control dpd1 clients-date-input" value="2026-07-10" name="start"><span class="input-group-addon inputsDivider">ից</span><input type="date" class="form-control dpd2 clients-date-input" value="2026-07-10" name="end"><span class="input-group-btn"><button class="btn btn-md btn-info padding5" type="button">Ֆիլտրել</button></span></div></form></div>
        <div class="clients-expenses-action-row"><div class="clients-expenses-selection-actions"><button class="btn btn-info" id="selectAllDebts" type="button">Նշել բոլորը</button><button class="btn btn-warning" id="paySelectedDebts" type="button" data-toggle="modal" data-target="#paySelectedDebtsModal">Մարել նշվածները</button></div><a class="dt-button buttons-excel buttons-html5" tabindex="0"><span><button class="btn btn-primary excelButton" type="button"><img src="assets/img/icons/ExcelLogo.svg" alt=""> Excel</button></span></a></div>
        <div class="clients-expenses-page-size"><label>Ցույց տալ <select class="form-control input-sm"><option selected>10</option><option>25</option><option>50</option></select> գրառում</label></div>
        <div class="clients-expenses-table-wrap">
          <table class="table table-striped table-bordered" id="clientGridTable">
            <thead>
              <tr><th></th><th>Ամսաթիվ</th><th>Կտրոն</th><th>Սեղան</th><th>Հաճախորդ</th><th>Ընդհանուր</th><th>Մարված</th><th>Մնացորդ</th><th>Մեկնաբանություն</th><th class="cogs"><i class="fa fa-cogs" aria-hidden="true"></i></th></tr>
              <tr class="filters"><td></td>${Array.from({ length: 8 }).map((_, index) => `<td><input type="text" class="form-control${index === 6 ? ' forPay' : ''}" placeholder=""></td>`).join('')}<td></td></tr>
            </thead>
            <tbody>${tableRows}</tbody>
            <tfoot><tr><td></td><td>Total</td><td></td><td></td><td></td><td id="totalPriceFooter">52300</td><td id="payedFooter">14600</td><td id="balanceFooter">37700</td><td></td><td></td></tr></tfoot>
          </table>
        </div>
        <button class="btn btn-info payAll pull-right" data-mediatedAmount="1885" data-mediatedPercent="5" data-id="1" value="37700" type="button" data-toggle="modal" data-target="#payClientDebtModal">Մարել ամբողջը</button>
      </div>
    </section>
    <div id="payModal" class="modal fade clients-debt-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button data-dismiss="modal" class="close" type="button">&times;</button><h4 class="modal-title">Մարել պարտքը</h4></div><div class="modal-body"><div class="row"><div class="form-group"><label class="control-label col-sm-4">Գումարի չափը</label><div class="col-sm-8"><input type="number" name="money" class="form-control m-bot15" value="19500"></div></div><div class="form-group"><label class="control-label col-sm-4">Դրամարկղ</label><div class="col-sm-8"><select class="form-control" name="cashbox"><option>Ընդհանուր</option><option>Կանխիկ</option><option>Բանկային</option></select></div></div></div></div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button type="button" class="btn btn-success pull-right" id="paySubmit">Հաստատել</button></div></div></div></div>
    <div id="payClientDebtModal" class="modal fade clients-debt-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Մարել ամբողջ պարտքը</h4></div><div class="modal-body form-horizontal"><div class="form-group"><label class="control-label col-sm-4">Գումար:</label><div class="col-sm-8"><input type="number" class="form-control" name="payed" value="37700"></div></div><div class="form-group"><label class="control-label col-sm-4">Միջնորդավորված գումարի տոկոս</label><div class="col-sm-8"><input type="number" name="mediatedPercent" disabled class="form-control m-bot15" value="5"></div></div><div class="form-group"><label class="control-label col-sm-4">Միջնորդավորված գումարի չափը</label><div class="col-sm-8"><input type="number" name="mediatedAmount" disabled class="form-control m-bot15" value="1885"></div></div><div class="form-group"><label class="control-label col-sm-4">Մուտք դրամարկղ</label><div class="col-sm-8"><input type="number" name="cache-box-amount" disabled class="form-control m-bot15" value="35815"></div></div><div class="form-group"><label class="control-label col-sm-4">Դրամարկղ:</label><div class="col-sm-8"><select class="form-control" name="cashbox"><option>Ընդհանուր</option><option>Կանխիկ</option><option>Բանկային</option></select></div></div></div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-success pull-right payClientDebtSubmit">Հաստատել</button></div></div></div></div>
    <div id="paySelectedDebtsModal" class="modal fade clients-debt-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button data-dismiss="modal" class="close" type="button">&times;</button><h4 class="modal-title">Մարել պարտքը</h4></div><div class="modal-body"><div class="row"><div class="form-group"><label class="control-label col-sm-4">Գումարի չափը</label><div class="col-sm-8"><input disabled type="number" name="money" class="form-control m-bot15" value="37700"></div></div><div class="form-group"><label class="control-label col-sm-4">Դրամարկղ</label><div class="col-sm-8"><select class="form-control" name="cashbox"><option>Ընդհանուր</option><option>Կանխիկ</option><option>Բանկային</option></select></div></div><div class="form-group"><div class="col-sm-8 col-sm-offset-4"><input checked type="checkbox" name="need_fiscal" id="needFiscalCheckbox"><label for="needFiscalCheckbox" class="control-label"> Տպել ՀԴՄ անդորագիր</label></div></div></div><div data-id="5" id="payClientDebtErrorsContainer"></div></div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><div class="pull-right"><button type="button" class="btn btn-danger" id="printSelectedDebts">Տպել Հաշիվը</button><button type="button" class="btn btn-success" id="paySelectedDebtsSubmit">Հաստատել</button></div></div></div></div></div>
  </div>`;
}

function staffPresentContent() {
  const rows = [
    ['Վազգեն', 'Մինասյան', 'Երաշխիչ', '2026-01-01', '2026-07-11', '171ժամ 55րոպե'],
    ['Մարի', 'Փոդրյան', 'Աման լվացող / Հավաքարար', '2026-01-01', '2026-07-11', '150ժամ 41րոպե'],
    ['Երաշխիչ', 'Երաշխյան', 'Երաշխիչ', '2026-01-01', '2026-07-11', '171ժամ 55րոպե'],
    ['սասա', 'սասսա', 'Երաշխիչ', '2026-01-01', '2026-07-11', '21ժամ 16րոպե'],
    ['vardges', 'Movsisyan', 'Մատուցող(ուհի)', '2026-01-01', '2026-07-11', '172ժամ 4րոպե'],
    ['Arman', 'Minasyan', 'Աման լվացող / Հավաքարար', '2026-01-01', '2026-07-11', '0ժամ 6րոպե'],
    ['Anna', 'Azaryan', 'Գանձապահ', '2026-01-01', '2026-07-11', '150ժամ 41րոպե'],
  ];
  const bodyRows = rows.map(row => `<tr>${row.map(cell => `<td>${cell}</td>`).join('')}</tr>`).join('');
  const searchRow = Array.from({ length: 6 }).map(() => '<td><input type="text" class="form-control input-sm" placeholder=""></td>').join('');

  return `<div class="staff-present-page">
    <div class="staff-present-buttons buttonsContainer">
      <a href="staff-detailed-presence.html" class="detailsButton">Մանրամասն</a>
      <form class="filterForm">
        <div class="inputContainer">
          <label for="filterStartDate" class="sr-only">Սկիզբ</label>
          <input type="date" id="filterStartDate" name="filterStartDate" value="2026-01-01">
          <span class="filterInputsSeparator">ից</span>
          <label for="filterEndDate" class="sr-only">Ավարտ</label>
          <input type="date" id="filterEndDate" name="filterEndDate" value="2026-07-11">
        </div>
        <button id="submitFilterButton" type="submit">ֆիլտրել</button>
      </form>
    </div>
    <div class="staff-present-table-tools">
      <div class="staff-present-export-buttons">
        <button class="btn btn-warning printButton" type="button"><i class="fa fa-print"></i> Տպել</button>
        <button class="btn btn-primary excelButton" type="button"><i class="fa fa-file-excel-o"></i> Excel</button>
      </div>
      <div class="staff-present-search form-inline">
        <label>Փնտրել <input type="text" class="form-control input-sm"></label>
      </div>
    </div>
    <div class="staff-present-table-wrap">
      <table id="presenceTable" class="table table-advance table-bordered">
        <thead>
          <tr>
            <th>Անուն</th>
            <th>Ազգանուն</th>
            <th>Հաստիք</th>
            <th>Ամսաթիվ սկիզբ</th>
            <th>Ամսաթիվ ավարտ</th>
            <th>Ընդհանուր ժամաքանակ</th>
          </tr>
          <tr class="column-search">${searchRow}</tr>
        </thead>
        <tbody>${bodyRows}</tbody>
      </table>
    </div>
    <div class="staff-present-footer">
      <div>Ցուցադրված է 1-ից 7-ը 7 տողից</div>
      <div class="staff-present-pagination">
        <button class="btn btn-default btn-sm disabled">Նախորդը</button>
        <button class="btn btn-danger btn-sm active">1</button>
        <button class="btn btn-default btn-sm disabled">Հաջորդը</button>
      </div>
    </div>
  </div>`;
}

function staffDetailedPresenceContent() {
  const rows = [
    ['Վազգեն', 'Մինասյան', 'Երաշխիչ', '2026-01-01 09:00:00', '2026-01-01 18:30:00', '9ժամ 30րոպե'],
    ['Մարի', 'Փոդրյան', 'Աման լվացող / Հավաքարար', '2026-01-02 10:10:00', '2026-01-02 19:40:00', '9ժամ 30րոպե'],
    ['Երաշխիչ', 'Երաշխյան', 'Երաշխիչ', '2026-01-03 08:45:00', '2026-01-03 18:15:00', '9ժամ 30րոպե'],
    ['սասա', 'սասսա', 'Երաշխիչ', '2026-01-04 12:20:00', '2026-01-04 16:50:00', '4ժամ 30րոպե'],
    ['vardges', 'Movsisyan', 'Մատուցող(ուհի)', '2026-01-05 09:05:00', '2026-01-05 18:10:00', '9ժամ 5րոպե'],
    ['Arman', 'Minasyan', 'Աման լվացող / Հավաքարար', '2026-01-06 11:00:00', '2026-01-06 11:06:00', '0ժամ 6րոպե'],
    ['Anna', 'Azaryan', 'Գանձապահ', '2026-01-07 10:00:00', '2026-01-07 18:15:00', '8ժամ 15րոպե'],
  ];
  const tableRows = rows.map(row => `<tr>${row.map(cell => `<td>${cell}</td>`).join('')}</tr>`).join('');
  const filters = Array.from({ length: 6 }).map(() => '<td><input type="text" class="form-control input-sm"></td>').join('');

  return `<div class="staff-detailed-presence-page">
    <form class="staff-detailed-filter" role="form">
      <div class="input-group input-large padding-left0 header_filter" data-date-format="yyyy/mm/dd" id="noPadL">
        <input type="date" class="form-control dpd1" value="2026-01-01" name="start">
        <span class="input-group-addon inputsDivider">ից</span>
        <input type="date" class="form-control dpd2" value="2026-07-11" name="end">
        <span class="input-group-btn"><button class="btn btn-md btn-info padding5" type="submit">Ֆիլտրել</button></span>
      </div>
    </form>
    <section class="panel staff-detailed-panel">
      <div class="panel-body">
        <div class="staff-detailed-toolbar">
          <div class="staff-detailed-page-size">
            <label>Ցույց տալ <select class="form-control input-sm"><option selected>10</option><option>25</option><option>50</option></select> գրառում</label>
          </div>
          <a class="dt-button buttons-excel buttons-html5 excelButton" tabindex="0"><span><button class="btn btn-primary" type="button"><i class="fa fa-file-excel-o"></i> Excel</button></span></a>
        </div>
        <div class="staff-detailed-table-wrap">
          <table class="table table-striped table-bordered" id="presentGridTable">
            <thead>
              <tr>
                <th>Անուն</th>
                <th>Ազգանուն</th>
                <th>Հաստիք</th>
                <th>Սկիզբ</th>
                <th>Ավարտ</th>
                <th>Ժամանակ</th>
              </tr>
              <tr class="filters">${filters}</tr>
            </thead>
            <tbody>${tableRows}</tbody>
            <tfoot><tr><td>ընդհանուր</td><td></td><td></td><td></td><td></td><td>50ժամ 26րոպե</td></tr></tfoot>
          </table>
        </div>
        <div class="staff-detailed-footer">
          <div>Ցուցադրված է 1-ից 7-ը 7 տողից</div>
          <div class="staff-detailed-pagination">
            <button class="btn btn-default btn-sm disabled">Նախորդը</button>
            <button class="btn btn-danger btn-sm active">1</button>
            <button class="btn btn-default btn-sm disabled">Հաջորդը</button>
          </div>
        </div>
      </div>
    </section>
  </div>`;
}

function staffExpensesContent() {
  const rows = [
    ['Anna Azaryan', 'Գանձապահ', '44000'],
    ['Վազգեն Մինասյան', 'Երաշխիչ', '0'],
    ['vardges Movsisyan', 'Մատուցող(ուհի)', '0'],
    ['Մարի Փոդյան', 'Աման լվացող / Հավաքարար', '24000'],
    ['սասա սասսա', 'Երաշխիչ', '200000'],
    ['Երաշխիչ Երաշխյան', 'Երաշխիչ', '0'],
  ];
  const bodyRows = rows.map((row, index) => `<tr data-id="${index + 1}">
      <td>${row[0]} <a class="btn btn-xs btn-success staff-expenses-detail-link" href="staff-expenses.html"><i class="icon-chevron-right"></i></a></td>
      <td>${row[1]}</td>
      <td>${row[2]}</td>
    </tr>`).join('');

  return `<div class="staff-expenses-page">
    <div class="row">
      <div class="col-lg-12 pad_320">
        <section class="panel staff-expenses-panel">
          <div class="panel-body pad_320">
            <div class="staff-expenses-total-actions">
              <button class="btn btn-warning staff-expenses-print" type="button"><i class="fa fa-print"></i> Տպել</button>
              <button class="btn btn-success staff-expenses-excel" type="button"><i class="fa fa-file-excel-o"></i> Excel</button>
            </div>
            <div class="staff-expenses-total-search form-inline text-right">
              <label>Փնտրել <input type="text" class="form-control input-sm"></label>
            </div>
            <div class="adv-table staff-expenses-table-wrap staff-expenses-total-table-wrap">
              <table class="table table-bordered mytable expenses_total_table">
                <thead>
                  <tr>
                    <th>Անուն ազգանուն</th>
                    <th>Հաստիք</th>
                    <th>Պարտք</th>
                  </tr>
                  <tr class="column-search">
                    <td><input type="text" class="form-control input-sm" placeholder="Փնտրել"></td>
                    <td><input type="text" class="form-control input-sm" placeholder="Փնտրել"></td>
                    <td><input type="text" class="form-control input-sm" placeholder="Փնտրել"></td>
                  </tr>
                </thead>
                <tbody>${bodyRows}</tbody>
                <tfoot><tr><th></th><th></th><th>268000</th></tr></tfoot>
              </table>
            </div>
            <div class="staff-expenses-total-footer">
              <div>Ցուցադրված է 1-ից 6-ը 6 տողից</div>
              <div class="staff-expenses-pagination">
                <button class="btn btn-default btn-sm disabled">Նախորդը</button>
                <button class="btn btn-danger btn-sm active">1</button>
                <button class="btn btn-default btn-sm disabled">Հաջորդը</button>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>
    <input type="hidden" class="excelData" value='[{"lineName":"Ծրագրի անվանում","lineValue":"Smart Rest"},{"lineName":"Ծրագրի բաժին","lineValue":"Աշխատակազմ / Աշխատակիցների Պարտքեր"}]'>
  </div>`;
}

function staffSalaryTypesContent() {
  const rows = [
    ['Ժամային տոկոս', 'hour_percent', 'Այո', 'Ոչ', 'Այո', 'Ոչ', 'Ոչ'],
    ['Օրական ֆիքսված', 'daily_fixed', 'Ոչ', 'Այո', 'Ոչ', 'Այո', 'Ոչ'],
    ['Ամսական աշխատավարձ', 'monthly_fixed', 'Ոչ', 'Այո', 'Ոչ', 'Ոչ', 'Այո'],
    ['Վաճառքից տոկոս', 'sales_percent', 'Այո', 'Ոչ', 'Ոչ', 'Ոչ', 'Ոչ'],
  ];
  const bodyRows = rows.map((row, index) => `<tr data-id="${index + 1}">
      ${row.map(cell => `<td>${cell}</td>`).join('')}
      <td class="cog_btns_td"><div class="flexBtns"><a href="#editSalaryTypeModal" data-toggle="modal" class="btn btn-warning btn-xs inTableIconButton"><img src="assets/img/icons/pencil.svg" alt=""></a><a href="#delSalaryTypeModal" data-toggle="modal" class="btn btn-danger btn-xs inTableIconButton"><img src="assets/img/icons/trash.svg" alt=""></a></div></td>
    </tr>`).join('');
  const filters = Array.from({ length: 7 }).map(() => '<td><input type="text" class="form-control input-sm"></td>').join('');

  const typeForm = (mode) => `<form class="form-horizontal text-center submitFrom">
      <div class="form-group"><label class="control-label col-sm-3">Անվանում:</label><div class="col-sm-9"><input type="text" class="form-control" name="StaffSalaryTypesForm[title]"${mode === 'edit' ? ' value="Ժամային տոկոս"' : ''}></div></div>
      <div class="form-group"><label class="control-label col-sm-3">Տիպ:</label><div class="col-sm-9"><input type="text" class="form-control" name="StaffSalaryTypesForm[type]"${mode === 'edit' ? ' value="hour_percent"' : ''}></div></div>
      <div class="form-group"><label class="control-label col-sm-3">տոկոսավճար</label><div class="col-sm-9"><select class="form-control" name="StaffSalaryTypesForm[isPercent]"><option>No</option><option${mode === 'edit' ? ' selected' : ''}>Yes</option></select></div></div>
      <div class="form-group"><label class="control-label col-sm-3">ֆիքսված</label><div class="col-sm-9"><select class="form-control" name="StaffSalaryTypesForm[isFixed]"><option${mode === 'edit' ? ' selected' : ''}>No</option><option>Yes</option></select></div></div>
      <div class="form-group"><label class="control-label col-sm-3">ժամավճար</label><div class="col-sm-9"><select class="form-control" name="StaffSalaryTypesForm[isHourly]"><option>No</option><option${mode === 'edit' ? ' selected' : ''}>Yes</option></select></div></div>
      <div class="form-group"><label class="control-label col-sm-3">օրավճար</label><div class="col-sm-9"><select class="form-control" name="StaffSalaryTypesForm[isDaily]"><option selected>No</option><option>Yes</option></select></div></div>
      <div class="form-group"><label class="control-label col-sm-3">ամսական</label><div class="col-sm-9"><select class="form-control" name="StaffSalaryTypesForm[isMonthly]"><option selected>No</option><option>Yes</option></select></div></div>
    </form>`;

  return `<div class="staff-salary-types-page">
    <div class="row">
      <div class="col-lg-12 pad_320">
        <section class="panel staff-salary-types-panel">
          <header class="panel-heading staff-salary-types-heading">
            <button href="#addSalaryTypeModal" data-toggle="modal" type="button" class="btn btn-success add-salary-type-btn"><img src="assets/img/icons/plusIcon.svg" alt=""> Ավելացնել տիպ</button>
          </header>
          <div class="panel-body pad_320">
            <div class="staff-salary-types-toolbar">
              <div class="staff-salary-types-page-size"><label>Ցույց տալ <select class="form-control input-sm"><option selected>10</option><option>25</option><option>50</option></select> գրառում</label></div>
              <a class="dt-button buttons-excel buttons-html5 ExcelButton" tabindex="0"><span><button class="btn btn-primary" type="button"><img src="assets/img/icons/ExcelLogo.svg" alt=""> Excel</button></span></a>
            </div>
            <div class="staff-salary-types-table-wrap">
              <table class="table table-striped table-bordered" id="companyGridTable">
                <thead>
                  <tr>
                    <th>Անվանում</th>
                    <th>Տիպ</th>
                    <th>Տոկոսավճար</th>
                    <th>Ֆիքսված</th>
                    <th>Ժամավճար</th>
                    <th>Օրավճար</th>
                    <th>Ամսական</th>
                    <th class="cogs"><i class="fa fa-cogs" aria-hidden="true"></i></th>
                  </tr>
                  <tr class="filters">${filters}<td></td></tr>
                </thead>
                <tbody>${bodyRows}</tbody>
              </table>
            </div>
            <div class="staff-salary-types-footer">
              <div>Ցուցադրված է 1-ից 4-ը 4 տողից</div>
              <div class="staff-salary-types-pagination"><button class="btn btn-default btn-sm disabled">Նախորդը</button><button class="btn btn-danger btn-sm active">1</button><button class="btn btn-default btn-sm disabled">Հաջորդը</button></div>
            </div>
          </div>
        </section>
      </div>
    </div>
    <div id="addSalaryTypeModal" class="modal fade staff-salary-type-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button aria-hidden="true" data-dismiss="modal" class="close" type="button">&times;</button><h4 class="modal-title">Ավելացնել տիպ</h4></div><div class="modal-body">${typeForm('add')}</div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-success submit pull-right" type="button">Հաստատել</button></div></div></div></div>
    <div id="editSalaryTypeModal" class="modal fade staff-salary-type-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button aria-hidden="true" data-dismiss="modal" class="close" type="button">&times;</button><h4 class="modal-title">Ավելացնել տիպ</h4></div><div class="modal-body">${typeForm('edit')}</div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-success submit pull-right" type="button">Հաստատել</button></div></div></div></div>
    <div id="delSalaryTypeModal" class="modal fade staff-salary-type-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button aria-hidden="true" data-dismiss="modal" class="close" type="button">&times;</button><h4 class="modal-title">Ջնջել Աշխատակցին</h4></div><div class="modal-body"><form class="form-horizontal text-center"><div class="form-group"><label class="col-lg-12 control-label text-center" style="font-size:20px;">Դուք համոզված ե՞ք ջնջել</label></div></form></div><div class="modal-footer"><button class="btn btn-danger markAsDeleted" type="button">Հաստատել</button></div></div></div></div>
  </div>`;
}

function staffSalaryValuesContent() {
  const rows = [
    ['Արամ Սարգսյան', 'Հիմնական սրահ', 'Հիմնական սրահ - Խմիչքներ - Սուրճ', 'Ժամային տոկոս', '5'],
    ['Նարե Մկրտչյան', 'Դահլիճ 1', '-', 'Ամսական աշխատավարձ', '180000'],
    ['Գոռ Մարտիրոսյան', '-', 'Խոհանոց - Ուտեստներ - Սթեյք', 'Վաճառքից տոկոս', '3'],
    ['Մարի Գրիգորյան', 'Բար', 'Բար - Խմիչքներ - Կոկտեյլ', 'Օրական ֆիքսված', '8000'],
  ];
  const bodyRows = rows.map((row, index) => `<tr data-id="${index + 1}">
      ${row.map(cell => `<td>${cell}</td>`).join('')}
      <td class="cog_btns_td"><div class="flexBtns"><a href="#editSalaryValueModal" data-toggle="modal" class="btn btn-warning btn-xs inTableIconButton"><img src="assets/img/icons/pencil.svg" alt=""></a><a href="#delSalaryValueModal" data-toggle="modal" class="btn btn-danger btn-xs inTableIconButton"><img src="assets/img/icons/trash.svg" alt=""></a></div></td>
    </tr>`).join('');
  const filters = Array.from({ length: 5 }).map(() => '<td><input type="text" class="form-control input-sm"></td>').join('');

  const options = {
    staff: ['Արամ Սարգսյան', 'Նարե Մկրտչյան', 'Գոռ Մարտիրոսյան', 'Մարի Գրիգորյան'],
    menu: ['-', 'Հիմնական սրահ', 'Դահլիճ 1', 'Բար'],
    item: ['-', 'Հիմնական սրահ - Խմիչքներ - Սուրճ', 'Խոհանոց - Ուտեստներ - Սթեյք', 'Բար - Խմիչքներ - Կոկտեյլ'],
    type: ['Ժամային տոկոս', 'Օրական ֆիքսված', 'Ամսական աշխատավարձ', 'Վաճառքից տոկոս'],
  };
  const select = (name, list, selectedValue) => `<select class="form-control e9" name="${name}">${list.map(item => `<option${item === selectedValue ? ' selected' : ''}>${item}</option>`).join('')}</select>`;
  const valueForm = (mode) => `<form class="form-horizontal text-center submitFrom">
      <div class="form-group"><label class="control-label col-sm-3">Աշխատակից:</label><div class="col-sm-9">${select('StaffSalaryValuesForm[staffId]', options.staff, mode === 'edit' ? 'Արամ Սարգսյան' : 'Արամ Սարգսյան')}</div></div>
      <div class="form-group"><label class="control-label col-sm-3">Մենյու:</label><div class="col-sm-9">${select('StaffSalaryValuesForm[menuId]', options.menu, mode === 'edit' ? 'Հիմնական սրահ' : '-')}</div></div>
      <div class="form-group"><label class="control-label col-sm-3">Ապրանք:</label><div class="col-sm-9">${select('StaffSalaryValuesForm[menuItemId]', options.item, mode === 'edit' ? 'Հիմնական սրահ - Խմիչքներ - Սուրճ' : '-')}</div></div>
      <div class="form-group"><label class="control-label col-sm-3">Տիպ:</label><div class="col-sm-9">${select('StaffSalaryValuesForm[typeId]', options.type, mode === 'edit' ? 'Ժամային տոկոս' : 'Ժամային տոկոս')}</div></div>
      <div class="form-group"><label class="control-label col-sm-3">Արժեք:</label><div class="col-sm-9"><input type="text" class="form-control" name="StaffSalaryValuesForm[value]"${mode === 'edit' ? ' value="5"' : ''}></div></div>
    </form>`;

  return `<div class="staff-salary-types-page staff-salary-values-page">
    <div class="row">
      <div class="col-lg-12 pad_320">
        <section class="panel staff-salary-types-panel">
          <header class="panel-heading staff-salary-types-heading">
            <button href="#addSalaryValueModal" data-toggle="modal" type="button" class="btn btn-success add-salary-type-btn"><img src="assets/img/icons/plusIcon.svg" alt=""> Սահմանել աշխատավարձ</button>
          </header>
          <div class="panel-body pad_320">
            <div class="staff-salary-types-toolbar">
              <div class="staff-salary-types-page-size"><label>Ցույց տալ <select class="form-control input-sm"><option selected>10</option><option>25</option><option>50</option></select> գրառում</label></div>
              <a class="dt-button buttons-excel buttons-html5 ExcelButton" tabindex="0"><span><button class="btn btn-primary" type="button"><img src="assets/img/icons/ExcelLogo.svg" alt=""> Excel</button></span></a>
            </div>
            <div class="staff-salary-types-table-wrap">
              <table class="table table-striped table-bordered" id="companyGridTable">
                <thead>
                  <tr>
                    <th>Աշխատակից</th>
                    <th>Մենյու</th>
                    <th>Ապրանք</th>
                    <th>Տիպ</th>
                    <th>Արժեք</th>
                    <th class="cogs"><i class="fa fa-cogs" aria-hidden="true"></i></th>
                  </tr>
                  <tr class="filters">${filters}<td></td></tr>
                </thead>
                <tbody>${bodyRows}</tbody>
              </table>
            </div>
            <div class="staff-salary-types-footer">
              <div>Ցուցադրված է 1-ից 4-ը 4 տողից</div>
              <div class="staff-salary-types-pagination"><button class="btn btn-default btn-sm disabled">Նախորդը</button><button class="btn btn-danger btn-sm active">1</button><button class="btn btn-default btn-sm disabled">Հաջորդը</button></div>
            </div>
          </div>
        </section>
      </div>
    </div>
    <div id="addSalaryValueModal" class="modal fade staff-salary-type-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button aria-hidden="true" data-dismiss="modal" class="close" type="button">&times;</button><h4 class="modal-title">Ավելացնել տիպ</h4></div><div class="modal-body">${valueForm('add')}</div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-success submit pull-right" type="button">Հաստատել</button></div></div></div></div>
    <div id="editSalaryValueModal" class="modal fade staff-salary-type-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button aria-hidden="true" data-dismiss="modal" class="close" type="button">&times;</button><h4 class="modal-title">Ավելացնել տիպ</h4></div><div class="modal-body">${valueForm('edit')}</div><div class="modal-footer"><button type="button" class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-success submit pull-right" type="button">Հաստատել</button></div></div></div></div>
    <div id="delSalaryValueModal" class="modal fade staff-salary-type-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button aria-hidden="true" data-dismiss="modal" class="close" type="button">&times;</button><h4 class="modal-title">Ջնջել Աշխատակցին</h4></div><div class="modal-body"><form class="form-horizontal text-center"><div class="form-group"><label class="col-lg-12 control-label text-center" style="font-size:20px;">Դուք համոզված ե՞ք ջնջել</label></div></form></div><div class="modal-footer"><button class="btn btn-danger markAsDeleted" type="button">Հաստատել</button></div></div></div></div>
  </div>`;
}

function fastFoodHandlerContent() {
  const orders = [
    { id: 1042, uniq: 10042, status: 'waiting', state: 'Սպասում է', metaIcon: 'fa-map-marker', meta: 'Main Hall / Սեղան 8', time: '10:18', items: ['Բուրգեր x2', 'Ֆրի x1', 'Կոլա x2'] },
    { id: 1047, uniq: 10047, status: 'ready', state: 'Պատրաստ է', metaIcon: 'fa-map-marker', meta: 'Terrace / Սեղան 3', time: '10:24', items: ['Պիցցա x1', 'Աղցան x1'] },
    { id: 1051, uniq: 10051, status: 'waiting', state: 'Ուշանում է', hot: true, metaIcon: 'fa-shopping-bag', meta: 'Take away', time: '10:31', items: ['Շաուրմա x3', 'Թան x3'] },
    { id: 1058, uniq: 10058, status: 'ready', state: 'Պատրաստ է', metaIcon: 'fa-motorcycle', meta: 'Delivery', time: '10:39', items: ['Քաբաբ x4', 'Լավաշ x2', 'Աղցան x2'] },
    { id: 1060, uniq: 10060, status: 'waiting', state: 'Սպասում է', metaIcon: 'fa-map-marker', meta: 'Main Hall / Սեղան 11', time: '10:42', items: ['Սթեյք x1', 'Կարտոֆիլ x1'] },
    { id: 1062, uniq: 10062, status: 'ready', state: 'Պատրաստ է', metaIcon: 'fa-map-marker', meta: 'Terrace / Սեղան 5', time: '10:45', items: ['Լատե x2', 'Չիզքեյք x2'] },
  ];

  const cardHtml = orders.map(order => {
    const ready = order.status === 'ready';
    const classes = ['ready-order-card', ready ? 'is-ready' : 'is-waiting', order.hot ? 'is-hot' : ''].filter(Boolean).join(' ');
    const actionClass = ready ? 'handle_order' : 'make_ready';
    const actionText = ready ? 'Հանձնել' : 'Պատրաստ է';
    const btnClass = ready ? 'btn-success' : 'btn-info';
    const search = `պատվեր ${order.id} ${order.meta} ${order.state} ${order.items.join(' ')}`.toLowerCase();

    return `<article class="${classes}" data-status="${order.status}" data-search="${search}">
            <div class="ready-order-card-head">
              <span class="ready-order-number">Պատվեր No. ${order.id}</span>
              <span class="ready-order-state">${order.state}</span>
            </div>
            <div class="ready-order-meta">
              <span><i class="fa ${order.metaIcon}"></i> ${order.meta}</span>
              <span><i class="fa fa-clock-o"></i> ${order.time}</span>
            </div>
            <div class="ready-order-items">${order.items.map(item => `<span>${item}</span>`).join('')}</div>
            <button class="btn ${btnClass} btn-block ${actionClass}" data-uniq-id="${order.uniq}" type="button">${actionText}</button>
          </article>`;
  }).join('\n');

  return `<div class="ready-orders-page">
        <div class="ready-orders-hero">
          <a class="ready-orders-back" href="fast-food.html" title="Վերադառնալ արագ սնունդ"><i class="fa fa-chevron-left"></i></a>
          <div class="ready-orders-title">
            <span>SMART PRODUCTION</span>
            <h3>Պատրաստի պատվերներ</h3>
            <p>Խոհանոցից սպասարկում գնացող պատվերների արագ վերահսկում։ Նշեք պատրաստ պատվերը կամ հաստատեք հանձնումը մեկ սեղմումով։</p>
          </div>
          <div class="ready-orders-live"><span class="ready-orders-pulse"></span> Թարմացվում է 0.75 վրկ.</div>
        </div>

        <div class="ready-orders-stats">
          <div class="ready-orders-stat is-waiting"><span>Սպասում է պատրաստման</span><strong>4</strong><em>պետք է նշել պատրաստ</em></div>
          <div class="ready-orders-stat is-ready"><span>Պատրաստ է</span><strong>3</strong><em>սպասում է հանձնման</em></div>
          <div class="ready-orders-stat is-done"><span>Հանձնված այսօր</span><strong>28</strong><em>վերջին հանձնումը 2ր. առաջ</em></div>
        </div>

        <div class="ready-orders-toolbar">
          <div class="ready-orders-search"><i class="fa fa-search"></i><input type="search" id="readyOrderSearch" class="form-control" placeholder="Փնտրել պատվերի համարով կամ սրահով"></div>
          <div class="ready-orders-filter" aria-label="Պատվերի ֆիլտր">
            <button type="button" class="active" data-filter="all">Բոլորը</button>
            <button type="button" data-filter="waiting">Սպասում է</button>
            <button type="button" data-filter="ready">Պատրաստ է</button>
          </div>
        </div>

        <div class="ready-orders-board" id="readyOrdersBoard">${cardHtml}</div>
        <div class="ready-orders-empty" id="readyOrdersEmpty">Պատվերներ չեն գտնվել</div>
      </div>
      <script>
        (function () {
          var search = document.getElementById('readyOrderSearch');
          var board = document.getElementById('readyOrdersBoard');
          var empty = document.getElementById('readyOrdersEmpty');
          var filterButtons = document.querySelectorAll('.ready-orders-filter button');
          var activeFilter = 'all';

          function applyReadyOrderFilter() {
            if (!board) return;
            var query = search ? search.value.trim().toLowerCase() : '';
            var visibleCount = 0;
            var cards = board.querySelectorAll('.ready-order-card');

            cards.forEach(function (card) {
              var matchesFilter = activeFilter === 'all' || card.getAttribute('data-status') === activeFilter;
              var matchesSearch = !query || (card.getAttribute('data-search') || '').toLowerCase().indexOf(query) !== -1;
              var isVisible = matchesFilter && matchesSearch;

              card.style.display = isVisible ? '' : 'none';
              if (isVisible) visibleCount += 1;
            });

            if (empty) empty.style.display = visibleCount ? 'none' : 'block';
          }

          filterButtons.forEach(function (button) {
            button.addEventListener('click', function () {
              filterButtons.forEach(function (item) {
                item.classList.remove('active');
              });
              button.classList.add('active');
              activeFilter = button.getAttribute('data-filter') || 'all';
              applyReadyOrderFilter();
            });
          });

          if (search) search.addEventListener('input', applyReadyOrderFilter);

          if (board) {
            board.addEventListener('click', function (event) {
              var button = event.target.closest('.make_ready, .handle_order');
              if (!button) return;
              button.classList.add('is-clicked');
              button.textContent = button.classList.contains('make_ready') ? 'Նշվեց պատրաստ' : 'Հանձնվեց';
            });
          }
        }());
      </script>`;
}

function pageContent(page) {
  if (page.page === 'ai-generator.html') return aiGeneratorContent();
  if (page.page === 'index.html') return dashboardContent();
  if (page.page === 'fast-food.html') return fastFoodContent();
  if (page.page === 'fast-food-handler.html') return fastFoodHandlerContent();
  if (page.page === 'rooms-hall.html') return roomsHallContent();
  if (page.page === 'rooms-hall-planning.html') return roomsHallPlanningContent();
  if (page.page === 'rooms-hall-tables.html') return roomsHallTablesContent();
  if (page.page === 'rooms-printer-error.html') return roomsPrinterErrorContent();
  if (page.page === 'rooms-fiscal-error.html') return roomsFiscalErrorContent();
  if (page.page === 'rooms-invisible-orders.html') return roomsInvisibleOrdersContent();
  if (page.page === 'rooms-kitchen.html') return roomsKitchenContent();
  if (page.page === 'rooms-tables.html') return roomsContent();
  if (page.page === 'rooms-table-order.html') return orderContent();
  if (page.page === 'rooms-add-order-item.html') return addOrderItemContent();
  if (page.page === 'store.html') return storeContent();
  if (page.page === 'store-material-category.html') return storeMaterialCategoryContent();
  if (page.page === 'store-items.html') return storeItemsContent();
  if (page.page === 'item.html') return storeItemDetailContent();
  if (page.page === 'store-documents.html') return storeDocumentsContent();
  if (page.page === 'store-balance.html') return storeBalanceContent();
  if (page.page === 'store-timeline.html') return storeTimelineContent();
  if (page.page === 'store-document-content.html') return storeDocumentDetailContent('content');
  if (page.page === 'store-document-submitted.html') return storeDocumentDetailContent('submitted');
  if (page.page === 'cash.html') return cashContent();
  if (page.page === 'cash-totalized.html') return cashTotalizedContent();
  if (page.page === 'cash-settings.html') return cashSettingsContent();
  if (page.page === 'expense-types.html') return expenseTypesContent();
  if (page.page === 'reports.html') return reportsContent();
  if (page.page === 'reports-check.html') return reportsCheckContent();
  if (page.page === 'reports-tables-history.html') return reportsTablesHistoryContent();
  if (page.page === 'reports-delivery.html') return reportsDeliveryContent();
  if (page.page === 'reports-moved-tables.html') return reportsMovedTablesContent();
  if (page.page === 'reports-moved-items.html') return reportsMovedItemsContent();
  if (page.page === 'reports-food.html') return reportsFoodContent();
  if (page.page === 'reports-combo.html') return reportsComboContent();
  if (page.page === 'reports-ingredients.html') return reportsIngredientsContent();
  if (page.page === 'reports-material-group-history.html') return reportsMaterialGroupHistoryContent();
  if (page.page === 'reports-logs.html') return reportsLogsContent();
  if (page.page === 'analysis-planning.html') return analysisPlanningContent();
  if (page.page === 'analysis-top-passive.html') return analysisTopPassiveContent();
  if (page.page === 'analysis-order-statistics.html') return analysisOrderStatisticsContent();
  if (page.page === 'analysis-sales-statistics.html') return analysisSalesStatisticsContent();
  if (page.page === 'menu.html') return menuContent();
  if (page.page === 'company.html') return companyContent();
  if (page.page === 'company-expenses.html') return companyExpensesContent();
  if (page.page === 'clients.html') return clientsContent();
  if (page.page === 'clients-cards-page.html') return clientsCardsPageContent();
  if (page.page === 'clients-cards.html') return clientsCardsHistoryContent();
  if (page.page === 'clients-response-settings.html') return clientsResponseSettingsContent();
  if (page.page === 'clients-response.html') return clientsResponseContent();
  if (page.page === 'clients-unserved.html') return clientsUnservedContent();
  if (page.page === 'clients-complaints.html') return clientsComplaintsContent();
  if (page.page === 'clients-expenses.html') return clientsExpensesContent();
  if (page.page === 'staff.html') return staffContent();
  if (page.page === 'staff-present.html') return staffPresentContent();
  if (page.page === 'staff-detailed-presence.html') return staffDetailedPresenceContent();
  if (page.page === 'staff-salary-types.html') return staffSalaryTypesContent();
  if (page.page === 'staff-salary-values.html') return staffSalaryValuesContent();
  if (page.page === 'present.html') return staffSalaryValuesContent();
  if (page.page === 'staff-expenses.html') return staffExpensesContent();
  if (page.page === 'incoming-orders.html') return incomingOrdersContent();
  if (page.page === 'reserve.html') return reserveContent();
  if (page.page === 'reserve-history.html') return reserveHistoryContent();
  if (page.page === 'settings.html') return settingsContent(page.title);
  if (page.page === 'settings-users.html') return settingsUsersContent();
  if (page.page === 'settings-checks-place.html') return settingsChecksPlaceContent();
  if (page.page === 'settings-sms.html') return settingsSmsContent();
  if (page.page === 'settings-meal-types.html') return settingsMealTypesContent();
  if (page.page === 'settings-branch.html') return settingsBranchContent();
  if (page.page === 'settings-user-branch.html') return settingsUserBranchContent();
  if (page.page === 'settings-archive-db.html') return settingsArchiveDbContent();
  if (page.page === 'admin-settings.html') return adminSettingsContent();
  if (page.page === 'settings-fiscal.html') return settingsFiscalContent();
  if (page.page === 'settings-hdm.html') return settingsHdmContent();
  if (page.page === 'settings-hdm-license.html') return settingsHdmLicenseContent();
  if (page.page === 'client-screen.html') return clientContent();
  if (page.page === 'hidden-popups.html') return hiddenContent();
  return genericContent(page);
}

function commonModals(page = {}) {
  const skipGenericCrudModals = ['settings-sms.html', 'settings-fiscal.html', 'settings-meal-types.html', 'settings-branch.html', 'settings-user-branch.html', 'settings-archive-db.html', 'admin-settings.html'].includes(page.page);
  const genericCrudModals = skipGenericCrudModals ? '' : `<div id="addModal" class="modal fade form-horizontal addModal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Ավելացնել</h4></div><div class="modal-body"><input class="form-control" placeholder="Անվանում"></div><div class="modal-footer"><button class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-success">Հաստատել</button></div></div></div></div>
<div id="editModal" class="modal fade form-horizontal editModal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Փոփոխել</h4></div><div class="modal-body"><input class="form-control" value="Demo"></div><div class="modal-footer"><button class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-warning">Հաստատել</button></div></div></div></div>
<div id="deleteModal" class="modal fade deleteModal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h4 class="modal-title">Ջնջել</h4></div><div class="modal-body"><p>Ջնջե՞լ նշված տողը</p></div><div class="modal-footer"><button class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-danger">Հաստատել</button></div></div></div></div>`;
  return `<div class="template-toast"></div>
<div id="dayEndModal" class="modal fade" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Օրվա վերջ</h4></div><div class="modal-body"><p>Օրվա վաճառքն ու չեքերի քանակը զրոյացնել։</p><input class="form-control sm-input" placeholder="Ադմինիստրատիվ մուտք"></div><div class="modal-footer"><button class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-danger">Հաստատել</button></div></div></div></div>
<div id="waiterPinModal" class="modal fade" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Մատուցողի ակտիվացում</h4></div><div class="modal-body text-center"><p style="font-size:19px">Մուտքագրեք Ձեր քարտի ID-ն</p><input type="password" class="form-control sm-input" style="width:250px;text-align:center;margin:10px auto" placeholder="Գաղտնաբառ"></div><div class="modal-footer"><button class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-success">Հաստատել</button></div></div></div></div>
<div id="discountModal" class="modal fade" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Զեղչել</h4></div><div class="modal-body"><label>Զեղչ %</label><input id="orderDiscountInput" class="form-control" value="10"></div><div class="modal-footer"><button class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button id="applyOrderDiscount" class="btn btn-warning">Հաստատել</button></div></div></div></div>
<div id="saleProductModal" class="modal fade" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Ապրանքի զեղչ</h4></div><div class="modal-body"><input id="productDiscountInput" class="form-control" placeholder="Զեղչ %"></div><div class="modal-footer"><button class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button id="applyProductDiscount" class="btn btn-success">Հաստատել</button></div></div></div></div>
<div id="clientModal" class="modal fade" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Ընտրել հաճախորդին</h4></div><div class="modal-body"><input id="clientNameInput" class="form-control" placeholder="Անուն / հեռախոս"></div><div class="modal-footer"><button class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button id="applyClientName" class="btn btn-primary">Պահպանել</button></div></div></div></div>
<div id="prepaymentModal" class="modal fade" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Կանխավճար</h4></div><div class="modal-body"><input id="prepaymentInput" class="form-control" value="5000"></div><div class="modal-footer"><button class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button id="applyPrepayment" class="btn btn-primary">Պահպանել</button></div></div></div></div>
<div id="closeOrderModal" class="modal fade" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h4 class="modal-title">Հաստատել և փակել</h4></div><div class="modal-body"><p>Փակե՞լ սեղանի պատվերը։</p></div><div class="modal-footer"><button class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button id="confirmCloseOrder" class="btn btn-info">Հաստատել</button></div></div></div></div>
<div id="zeroCloseModal" class="modal fade" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h4 class="modal-title">Զրոյացնել և փակել</h4></div><div class="modal-body"><p>Գործողությունը կփակի հաշիվը առանց գումարի։</p></div><div class="modal-footer"><button class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button id="confirmZeroCloseOrder" class="btn btn-danger">Հաստատել</button></div></div></div></div>
${genericCrudModals}
<div id="deleteDocContModal" class="modal fade" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h4 class="modal-title">Ջնջել հումքը</h4></div><div class="modal-body">Ջնջե՞լ նշված հումքը փաստաթղթից։</div><div class="modal-footer"><button class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-danger">Հաստատել</button></div></div></div></div>
<div id="editDocContModal" class="modal fade form-horizontal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Փոփոխել</h4></div><div class="modal-body"><input class="form-control" value="Լոլիկ"><input class="form-control" value="28" style="margin-top:8px"></div><div class="modal-footer"><button class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-warning">Հաստատել</button></div></div></div></div>
<div id="balanceErrorModal" class="modal fade" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Անբավարար մնացորդներ</h4></div><div class="modal-body"><table class="table table-bordered"><thead><tr><th>Material</th><th>Deficit</th><th>Products</th></tr></thead><tbody><tr><td>Սուրճ</td><td>2</td><td>Լատե</td></tr></tbody></table></div><div class="modal-footer"><button class="btn btn-default pull-left" data-dismiss="modal">Փակել</button></div></div></div></div>
<div id="setStaffModal" class="modal fade" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Վաճառող</h4></div><div class="modal-body"><select id="setStaffSelect" class="form-control"><option>Արամ Սարգսյան</option><option>Նարե Մկրտչյան</option><option>Գոռ Մարտիրոսյան</option></select></div><div class="modal-footer"><button class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button id="applySetStaff" class="btn btn-success">Հաստատել</button></div></div></div></div>
<div id="userModal" class="modal fade" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Օգտատեր</h4></div><div class="modal-body"><input class="form-control" value="waiter01"></div><div class="modal-footer"><button class="btn btn-default pull-left" data-dismiss="modal">Փակել</button><button class="btn btn-success">Պահպանել</button></div></div></div></div>
<div id="helperModal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true"><div class="modal-dialog" role="document"><div class="modal-content"><div class="modal-body"><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button><div><h1>SOON</h1></div></div></div></div></div>
<div id="welcomeClientModal" class="modal fade" role="dialog"><div class="modal-dialog"><div class="modal-content text-center"><div class="modal-body"><img src="assets/img/icons/clientWelcome.png" alt="" style="max-width:180px"><h2>Բարի գալուստ</h2><button class="btn btn-success" data-dismiss="modal">Սկսել</button></div></div></div></div>
<div id="waitCheckClientModal" class="modal fade" role="dialog"><div class="modal-dialog"><div class="modal-content text-center"><div class="modal-body"><img src="assets/img/icons/wait_check.png" alt="" style="max-width:120px"><h2>Սպասեք հաշիվը</h2><button class="btn btn-warning" data-dismiss="modal">Փակել</button></div></div></div></div>`;
}

const uniquePages = [...new Map([...pages, ...standalonePages].map(page => [page.page, page])).values()];

for (const page of uniquePages) {
  fs.writeFileSync(path.join(outDir, page.page), layout(page, pageContent(page)));
}

const pageList = uniquePages.map(page => `- ${page.page}: ${page.parent ? `${page.parent} / ` : ''}${page.title}`).join('\n');
fs.writeFileSync(path.join(outDir, 'PAGES.md'), `# Template pages\n\nGenerated by \`node build-pages.js\`.\n\n${pageList}\n`);
