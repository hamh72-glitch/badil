/* أدوات التفاعل العامة */
document.addEventListener('DOMContentLoaded', function () {
    var menuBtn = document.getElementById('menuBtn');
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sideOverlay');
    if (menuBtn && sidebar) {
        menuBtn.addEventListener('click', function () {
            sidebar.classList.add('open');
            if (overlay) overlay.classList.add('open');
        });
    }
    if (overlay) {
        overlay.addEventListener('click', function () {
            if (sidebar) sidebar.classList.remove('open');
            overlay.classList.remove('open');
        });
    }

    // إغلاق التنبيهات
    document.querySelectorAll('.alert').forEach(function (a) {
        setTimeout(function () {
            a.style.transition = 'opacity .4s';
            a.style.opacity = '0';
            setTimeout(function () { a.remove(); }, 400);
        }, 5000);
    });
});

function printArea(selector) {
    var el = document.querySelector(selector);
    if (!el) return;
    var original = document.body.innerHTML;
    document.body.innerHTML = el.outerHTML;
    window.print();
    document.body.innerHTML = original;
    location.reload();
}
