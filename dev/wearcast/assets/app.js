$(function () {
  const PHOTO_PLACEHOLDER = '\u753b\u50cf\u3092\u9078\u3076\u3068\u3053\u3053\u306b\u8868\u793a\u3055\u308c\u307e\u3059\u3002';
  const AREA_PLACEHOLDER = '\u5730\u57df\u3092\u9078\u629e';

  function previewImage(input, preview) {
    const file = input.files && input.files[0];
    const fileNameTarget = input.dataset.fileName ? $(input.dataset.fileName) : $();
    if (!file) {
      preview.empty().text(PHOTO_PLACEHOLDER);
      if (fileNameTarget.length) {
        fileNameTarget.text('\u672a\u9078\u629e');
      }
      return;
    }

    if (fileNameTarget.length) {
      fileNameTarget.text(file.name);
    }

    const reader = new FileReader();
    reader.onload = function (event) {
      preview.html($('<img>', { src: event.target.result, alt: 'preview' }));
    };
    reader.readAsDataURL(file);
  }

  $('[data-photo-input]').on('change', function () {
    const preview = $($(this).data('preview'));
    if (preview.length) {
      previewImage(this, preview);
    }
  });

  function loadAreas($office, presetArea) {
    const officeCode = $office.val();
    const $card = $office.closest('[data-location-card]');
    const $area = $card.find('[data-area-select]');
    const $prefecture = $card.find('[data-prefecture-name]');
    const $selectedOption = $office.find('option:selected');
    $prefecture.val($selectedOption.text());

    if (!officeCode) {
      $area.html('<option value="">' + AREA_PLACEHOLDER + '</option>');
      return;
    }

    $.getJSON(window.WEARCAST.baseUrl + '/api/areas.php', { office: officeCode }).done(function (response) {
      const options = ['<option value="">' + AREA_PLACEHOLDER + '</option>'];
      $.each(response.areas || [], function (_, area) {
        const selected = presetArea && presetArea === area.code ? ' selected' : '';
        options.push('<option value="' + area.code + '"' + selected + '>' + area.name + '</option>');
      });
      $area.data('loading-area', true);
      $area.html(options.join(''));
      $area.trigger('change');
      $area.removeData('loading-area');
    });
  }

  $('[data-office-select]').each(function () {
    const presetArea = $(this).attr('data-selected-area') || '';
    loadAreas($(this), presetArea);
  });

  $(document).on('change', '[data-office-select]', function () {
    loadAreas($(this), '');
  });

  $(document).on('change', '[data-area-select]', function () {
    const $option = $(this).find('option:selected');
    const $card = $(this).closest('[data-location-card]');
    $card.find('[data-region-name]').val($option.val() ? $option.text() : '');
  });

  $('[data-wear-select]').each(function () {
    const $select = $(this);
    const $button = $select.find('[data-wear-select-button]');
    const $label = $select.find('[data-wear-select-label]');
    const $sub = $select.find('[data-wear-select-sub]');

    function syncSelected() {
      const $checked = $select.find('input[type="radio"]:checked');
      const $ui = $checked.closest('.wear-option').find('.wear-option__ui');
      $label.text($ui.find('.wear-option__label').text());
      $sub.text($ui.find('.wear-option__sub').text());
    }

    syncSelected();

    $button.on('click', function () {
      const isOpen = $select.toggleClass('is-open').hasClass('is-open');
      $button.attr('aria-expanded', isOpen ? 'true' : 'false');
    });

    $select.on('change', 'input[type="radio"]', function () {
      syncSelected();
      $select.removeClass('is-open');
      $button.attr('aria-expanded', 'false');
    });
  });

  $(document).on('click', function (event) {
    const $target = $(event.target);
    $('[data-wear-select].is-open').each(function () {
      const $select = $(this);
      if (!$target.closest($select).length) {
        $select.removeClass('is-open').find('[data-wear-select-button]').attr('aria-expanded', 'false');
      }
    });
  });
});
