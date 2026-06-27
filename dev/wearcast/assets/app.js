$(function () {
  function previewImage(input, preview) {
    const file = input.files && input.files[0];
    if (!file) {
      preview.empty().text('画像を選ぶとここに表示されます');
      return;
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

  $('[data-current-location]').on('click', function () {
    const wrap = $(this).closest('[data-location-card]');
    if (!navigator.geolocation || !wrap.length) {
      return;
    }

    navigator.geolocation.getCurrentPosition(function (position) {
      wrap.find('input[name$="[lat]"]').val(position.coords.latitude.toFixed(6));
      wrap.find('input[name$="[lng]"]').val(position.coords.longitude.toFixed(6));
    });
  });

  function loadAreas($office, presetArea) {
    const officeCode = $office.val();
    const $card = $office.closest('[data-location-card]');
    const $area = $card.find('[data-area-select]');
    const $prefecture = $card.find('[data-prefecture-name]');
    const $selectedOption = $office.find('option:selected');
    $prefecture.val($selectedOption.text());

    if (!officeCode) {
      $area.html('<option value="">地域を選択</option>');
      return;
    }

    $.getJSON(window.WEARCAST.baseUrl + '/api/areas.php', { office: officeCode }).done(function (response) {
      const options = ['<option value="">地域を選択</option>'];
      $.each(response.areas || [], function (_, area) {
        const selected = presetArea && presetArea === area.code ? ' selected' : '';
        options.push('<option value="' + area.code + '"' + selected + '>' + area.name + '</option>');
      });
      $area.html(options.join(''));
      $area.trigger('change');
    });
  }

  $('[data-office-select]').each(function () {
    const presetArea = $(this).data('selected-area') || '';
    loadAreas($(this), presetArea);
  });

  $(document).on('change', '[data-office-select]', function () {
    loadAreas($(this), '');
  });

  $(document).on('change', '[data-area-select]', function () {
    const $option = $(this).find('option:selected');
    $(this).closest('[data-location-card]').find('[data-region-name]').val($option.text());
  });
});
