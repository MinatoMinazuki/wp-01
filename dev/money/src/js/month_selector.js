$(function () {
    function isMobileMonthSelector() {
        return window.matchMedia('(max-width: 600px)').matches;
    }

    $('.js-month-input').on('change', function () {
        if (isMobileMonthSelector()) {
            return;
        }

        if (this.value) {
            window.location.href = '?ym=' + this.value;
        }
    });

    $('.js-month-apply').on('click', function () {
        const value = $(this).closest('.month-selector').find('.js-month-input').val();
        if (!value) {
            return;
        }

        window.location.href = '?ym=' + value;
    });
});
