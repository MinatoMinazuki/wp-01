let selectedFiles = [];
let currentFileIndex = 0;
let isAnalyzing = false;

function getCurrentFile() {
    return selectedFiles[currentFileIndex] || null;
}

function clearResultForm() {
    const editForm = $('#edit-form')[0];
    if (editForm) {
        editForm.reset();
    }

    $('#edit-saved-images').val('');
    $('#items-tbody').empty();
    $('#result-container').hide();
}

function resetSelection() {
    selectedFiles = [];
    currentFileIndex = 0;
    $('#receipt-image').val('');
    $('#preview-grid').empty();
    $('#preview-status').text('');
    $('#preview-container').hide();
    $('#save-image-container').hide();
    clearResultForm();
}

function updateAnalyzeButton() {
    const $submitBtn = $('#submit-btn');
    const defaultText = $submitBtn.data('default-text') || $submitBtn.text().trim();
    const currentFile = getCurrentFile();

    if (!$submitBtn.data('default-text')) {
        $submitBtn.data('default-text', defaultText);
    }

    if (!currentFile) {
        $submitBtn.prop('disabled', true).text(defaultText);
        return;
    }

    const suffix = selectedFiles.length > 1 ? ` (${currentFileIndex + 1}/${selectedFiles.length})` : '';
    const buttonText = isAnalyzing ? `Analyzing...${suffix}` : `${defaultText}${suffix}`;

    $submitBtn.prop('disabled', isAnalyzing).text(buttonText);
}

function updatePreviewStatus() {
    const total = selectedFiles.length;

    if (!total) {
        $('#preview-status').text('');
        return;
    }

    const statusText = total > 1
        ? `Processing ${currentFileIndex + 1} / ${total}`
        : 'Processing 1 / 1';

    $('#preview-status').text(statusText);
}

function renderPreviewImages() {
    const $previewContainer = $('#preview-container');
    const $previewGrid = $('#preview-grid');
    const $saveImageContainer = $('#save-image-container');

    $previewGrid.empty();

    if (!selectedFiles.length) {
        $previewContainer.hide();
        $saveImageContainer.hide();
        updatePreviewStatus();
        updateAnalyzeButton();
        return;
    }

    selectedFiles.forEach(function (file, index) {
        const reader = new FileReader();
        const stateClass = index === currentFileIndex
            ? ' is-active'
            : index < currentFileIndex
                ? ' is-done'
                : '';
        const badgeText = index === currentFileIndex ? 'Now' : index < currentFileIndex ? 'Done' : `${index + 1}`;
        const $card = $('<div>', { class: `preview-card${stateClass}` });
        const $image = $('<img>', {
            class: 'preview-item',
            alt: file.name || `receipt-${index + 1}`
        });

        $('<span>', {
            class: 'preview-badge',
            text: badgeText
        }).appendTo($card);

        reader.onload = function (e) {
            const imageSrc = e.target.result;
            $image.attr('src', imageSrc);
            $image.on('click', function () {
                openModal(imageSrc);
            });
        };

        reader.readAsDataURL(file);
        $image.appendTo($card);
        $previewGrid.append($card);
    });

    $previewContainer.show();
    $saveImageContainer.show();
    updatePreviewStatus();
    updateAnalyzeButton();
}

function previewImages(input) {
    selectedFiles = Array.from(input.files || []);
    currentFileIndex = 0;
    clearResultForm();
    renderPreviewImages();
}

function openModal(src) {
    $('#modal-img').attr('src', src);
    $('#image-modal').css('display', 'flex');
}

function closeModal() {
    $('#image-modal').hide();
}

function addItemRow(name, price) {
    const itemName = name || '';
    const itemPrice = price || '';
    const $tbody = $('#items-tbody');
    const $tr = $('<tr>').append(`
        <td><input type="text" name="item_name[]" value="${itemName}" class="form-input" required></td>
        <td><input type="number" name="item_price[]" value="${itemPrice}" class="form-input" required></td>
        <td style="text-align: center;">
            <button type="button" class="btn btn-danger btn-remove btn-remove-responsive" title="Remove">x</button>
        </td>
    `);

    $tbody.append($tr);
}

function fillResultForm(data) {
    $('#edit-store-name').val(data.store_name || '');
    $('#edit-date').val(data.date || '');
    $('#edit-category').val(data.category || '');
    $('#edit-total-amount').val(data.total_amount || '');
    $('#edit-tax-amount').val(data.tax_amount || 0);
    $('#edit-saved-images').val(data.saved_images ? JSON.stringify(data.saved_images) : '');

    $('#items-tbody').empty();

    if (Array.isArray(data.items) && data.items.length > 0) {
        $.each(data.items, function (_, item) {
            addItemRow(item.name || '', item.price || '');
        });
    } else {
        addItemRow('', '');
    }

    $('#result-container').show();
}

function analyzeCurrentFile() {
    const currentFile = getCurrentFile();

    if (!currentFile || isAnalyzing) {
        return;
    }

    const formData = new FormData();
    const $loading = $('#loading');

    formData.append('receipt_images[]', currentFile);
    formData.append('save_image', $('#save-image').is(':checked') ? '1' : '0');

    isAnalyzing = true;
    updateAnalyzeButton();
    $('#result-container').hide();
    $loading.show();

    $.ajax({
        url: 'analyze_receipt.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function (data) {
            fillResultForm(data);
        },
        error: function (xhr) {
            console.error('Raw response:', xhr.responseText);
            const errorData = xhr.responseJSON || {};
            const msg = errorData.error || `Unknown error (${xhr.status} ${xhr.statusText})`;
            alert(`Analysis failed.\n${msg}`);
        },
        complete: function () {
            isAnalyzing = false;
            updateAnalyzeButton();
            $loading.hide();
        }
    });
}

$(function () {
    const $registerButton = $('#btn-register');
    const defaultRegisterText = $registerButton.text().trim();

    $('#items-tbody').on('click', '.btn-remove', function () {
        $(this).closest('tr').remove();
    });

    $('#upload-form').on('submit', function (e) {
        e.preventDefault();
        analyzeCurrentFile();
    });

    $('#btn-add-item').on('click', function () {
        addItemRow('', '');
    });

    $('#edit-form').on('submit', function (e) {
        e.preventDefault();

        const formData = new FormData(this);
        const submitData = Object.fromEntries(formData.entries());
        const itemNames = formData.getAll('item_name[]');
        const itemPrices = formData.getAll('item_price[]');

        submitData.items = [];

        for (let i = 0; i < itemNames.length; i++) {
            submitData.items.push({
                name: itemNames[i],
                price: itemPrices[i]
            });
        }

        const savedImagesJson = $('#edit-saved-images').val();
        if (savedImagesJson) {
            try {
                submitData.saved_images = JSON.parse(savedImagesJson);
            } catch (err) {
                console.error('Failed to parse saved_images:', err);
            }
        }

        $registerButton.prop('disabled', true).text('Saving...');

        $.ajax({
            url: 'save_receipt.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(submitData),
            success: function (data) {
                const hasNextFile = currentFileIndex < selectedFiles.length - 1;
                const message = `Saved.\n\nReceipt ID: ${data.receipt_id}\nItems: ${data.inserted_items}`;

                alert(message);
                clearResultForm();

                if (hasNextFile) {
                    currentFileIndex += 1;
                    renderPreviewImages();
                    analyzeCurrentFile();
                    return;
                }

                resetSelection();
                updateAnalyzeButton();
            },
            error: function (xhr) {
                const errorData = xhr.responseJSON || {};
                const msg = errorData.error || `Save failed (${xhr.status} ${xhr.statusText})`;
                alert(`Save failed.\n${msg}`);
            },
            complete: function () {
                $registerButton.prop('disabled', false).text(defaultRegisterText);
            }
        });
    });

    updateAnalyzeButton();
});
