const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

/* Table rows that open a page. Clicks on links, buttons and form controls keep their own behaviour. */
document.addEventListener('click', (event) => {
    const row = event.target.closest('tr[data-href]');

    if (row && !event.target.closest('a, button, input, select, textarea, form, summary')) {
        window.location.href = row.dataset.href;
    }
});

/* Forms that need a confirmation before submitting. */
document.addEventListener('submit', (event) => {
    const message = event.target.dataset.confirm;

    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
});

/* Selects that submit their form as soon as they change. */
document.addEventListener('change', (event) => {
    if (event.target.matches('select[data-autosubmit]')) {
        event.target.form.requestSubmit();
    }
});

/* Workflow step buttons on the ticket page: one form open at a time. */
document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-action-toggle]');

    if (!button) {
        return;
    }

    const name = button.dataset.actionToggle;
    const panel = $(`[data-action-panel="${name}"]`);
    const opening = panel.hidden;

    $$('[data-action-panel]').forEach((other) => (other.hidden = true));
    $$('[data-action-toggle]').forEach((other) => other.setAttribute('aria-expanded', 'false'));

    if (opening) {
        panel.hidden = false;
        $$(`[data-action-toggle="${name}"]`).forEach((other) => other.setAttribute('aria-expanded', 'true'));
        panel.querySelector('input:not([type=hidden]), select, textarea')?.focus();
    }
});

/* Phone photos are scaled down in the browser: they upload faster and stay under the server's size limit. */
const PHOTO_MAX_SIDE = 1920;
const PHOTO_SHRINK_FROM = 1024 * 1024;

async function shrinkPhoto(file) {
    if (!/^image\/(jpeg|png|webp)$/.test(file.type) || file.size <= PHOTO_SHRINK_FROM || !window.createImageBitmap) {
        return file;
    }

    try {
        const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
        const scale = Math.min(1, PHOTO_MAX_SIDE / Math.max(bitmap.width, bitmap.height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);

        const context = canvas.getContext('2d');
        context.fillStyle = '#fff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        bitmap.close();

        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.85));

        if (!blob || blob.size >= file.size) {
            return file;
        }

        return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg', lastModified: file.lastModified });
    } catch {
        return file;
    }
}

const formatSize = (bytes) => (bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`);

/* File inputs with a live preview; picking again adds to the selection instead of replacing it. */
function setUpFilePreview(input) {
    const target = document.getElementById(input.dataset.filePreview);
    const keyOf = (file) => `${file.name}|${file.size}|${file.lastModified}`;
    let entries = [];

    const sync = () => {
        const transfer = new DataTransfer();
        entries.forEach(({ file }) => transfer.items.add(file));
        input.files = transfer.files;
        render();
    };

    const render = () => {
        target.innerHTML = '';

        entries.forEach(({ file }, index) => {
            const isImage = file.type.startsWith('image/');
            const item = document.createElement('div');
            item.className = 'relative flex w-28 flex-col gap-1 rounded-md border border-line bg-panel p-1.5 text-[11px]';

            const thumb = document.createElement(isImage ? 'img' : 'div');
            thumb.className = 'h-20 w-full rounded object-cover bg-white grid place-items-center font-mono font-semibold text-muted';

            if (isImage) {
                thumb.src = URL.createObjectURL(file);
                thumb.alt = '';
            } else {
                thumb.textContent = 'PDF';
            }

            const name = document.createElement('span');
            name.className = 'truncate';
            name.title = file.name;
            name.textContent = file.name;

            const size = document.createElement('span');
            size.className = 'text-muted';
            size.textContent = formatSize(file.size);

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'absolute top-0.5 right-0.5 grid size-6 cursor-pointer place-items-center rounded-full bg-white/90 text-base leading-none text-muted shadow hover:text-red-700';
            remove.textContent = '×';
            remove.setAttribute('aria-label', `Bỏ ${file.name}`);
            remove.addEventListener('click', () => {
                entries = entries.filter((_, other) => other !== index);
                sync();
            });

            item.append(thumb, name, size, remove);
            target.append(item);
        });
    };

    input.addEventListener('change', async () => {
        const picked = [...input.files].filter((file) => !entries.some((entry) => entry.key === keyOf(file)));
        const buttons = input.form ? $$('button:not([type=button])', input.form) : [];
        buttons.forEach((button) => (button.disabled = true));

        try {
            const files = await Promise.all(picked.map(shrinkPhoto));
            picked.forEach((original, index) => entries.push({ key: keyOf(original), file: files[index] }));
        } finally {
            buttons.forEach((button) => (button.disabled = false));
            sync();
        }
    });
}

$$('input[type=file][data-file-preview]').forEach(setUpFilePreview);

/* Ticket intake form. */
const form = $('#ticket-form');

if (form) {
    const typeSelect = $('#device_type_id');
    const brandSelect = $('#brand_id');
    const modelSelect = $('#product_model_id');
    const serialInput = $('#serial_number');
    const customerSelect = $('#customer_id');
    const deviceBox = $('#device-box');
    const typeNames = JSON.parse(form.dataset.typeNames).map((name) => name.trim().toLowerCase());
    const brandNames = JSON.parse(form.dataset.brandNames);
    let customerTouched = customerSelect.value !== 'new';

    const checkedValue = (name) => form.querySelector(`input[name="${name}"]:checked`)?.value;

    const syncKind = () => {
        const outOfWarranty = checkedValue('warranty_status') === 'out_of_warranty';

        $$('input[name="kind"]', form).forEach((radio) => {
            radio.disabled = outOfWarranty && radio.value !== 'repair';
        });

        if (outOfWarranty) {
            form.querySelector('input[name="kind"][value="repair"]').checked = true;
        }

        const kind = checkedValue('kind');
        const radio = form.querySelector(`input[name="kind"][value="${kind}"]`);
        $('#kind-hint').textContent = (outOfWarranty ? 'Hết bảo hành: chỉ lập phiếu sửa chữa. ' : '') + (radio?.dataset.description ?? '');

        const isOnsite = kind === 'onsite' || kind === 'onsite_sangy';
        $$('[data-onsite-only]', form).forEach((element) => (element.hidden = !isOnsite));
        $$('[data-takes-device]', form).forEach((element) => (element.hidden = kind === 'onsite'));
        $('#photos').required = kind !== 'onsite';
        $$('[data-photo-required]', form).forEach((element) => (element.hidden = kind === 'onsite'));
        $$('[data-photo-optional]', form).forEach((element) => (element.hidden = kind !== 'onsite'));
        $$('[data-date-label]', form).forEach((element) => (element.textContent = kind === 'onsite' ? 'Ngày yêu cầu hãng' : 'Ngày nhận máy'));
    };

    const syncCustomer = () => {
        $$('[data-new-customer]', form).forEach((element) => (element.hidden = customerSelect.value !== 'new'));
    };

    const syncNewInputs = () => {
        const newType = typeSelect.value === 'new';
        const newBrand = brandSelect.value === 'new';
        const newModel = modelSelect.value === 'new';
        const typeName = $('#new_device_type').value.trim();
        const brandName = $('#new_brand').value.trim().toUpperCase();

        $('#new_device_type').hidden = !newType;
        $('#new_brand').hidden = !newBrand;
        $('#new_model_code').hidden = !newModel;

        $('[data-new-hint="device_type"]').textContent = !newType ? '' : typeNames.includes(typeName.toLowerCase()) ? `Đã có loại "${typeName}", sẽ dùng loại này.` : 'Loại mới sẽ được thêm vào danh mục.';
        $('[data-new-hint="brand"]').textContent = !newBrand ? '' : brandNames.includes(brandName) ? `Đã có hãng ${brandName}, sẽ dùng hãng này.` : 'Hãng mới sẽ được thêm vào danh mục (tên viết hoa).';
        $('[data-new-hint="model"]').textContent = newModel ? 'Model mới sẽ được thêm vào danh mục.' : '';
    };

    const loadModels = async (keep = null) => {
        const type = typeSelect.value;
        const brand = brandSelect.value;

        if (!type || !brand) {
            modelSelect.innerHTML = '<option value="">Chọn loại và hãng trước</option>';
            modelSelect.disabled = true;
            syncNewInputs();

            return;
        }

        modelSelect.disabled = false;

        if (type === 'new' || brand === 'new') {
            modelSelect.innerHTML = '<option value="new">+ Model mới…</option>';
            modelSelect.value = 'new';
            syncNewInputs();

            return;
        }

        const url = new URL(form.dataset.modelsUrl, window.location.origin);
        url.search = new URLSearchParams({ device_type_id: type, brand_id: brand });
        const models = await (await fetch(url, { headers: { Accept: 'application/json' } })).json();

        modelSelect.innerHTML = `<option value="">${models.length ? 'Chọn model…' : 'Chưa có model nào'}</option>`
            + models.map((model) => `<option value="${model.id}">${escapeHtml(model.code)}</option>`).join('')
            + '<option value="new">+ Model mới…</option>';

        if (keep && [...modelSelect.options].some((option) => option.value === String(keep))) {
            modelSelect.value = String(keep);
        }

        syncNewInputs();
    };

    /* Sang Y repair warranties still running on the device: what each covers, and the choice to claim one. */
    const levelPill = {
        active: 'bg-emerald-100 text-emerald-800',
        expiring: 'bg-amber-200 text-amber-900',
        expired: 'bg-red-100 text-red-800',
        none: 'bg-gray-100 text-gray-700',
    };
    let claimChoice = form.dataset.claimOld ?? '';

    const claimsHtml = (claims) => {
        if (!claims.some((claim) => String(claim.id) === claimChoice)) {
            claimChoice = '';
        }

        if (!claims.length) {
            return '';
        }

        const blocks = claims.map((claim) => {
            const items = claim.items.length
                ? claim.items.map((item) => `<li class="flex flex-wrap items-center justify-between gap-2 border-t border-dashed border-amber-300 py-1 first:border-0">
                        <span>${escapeHtml(item.description)} <span class="text-xs text-muted">· ${item.months} tháng</span></span>
                        <span class="rounded-full px-2 text-xs font-semibold ${levelPill[item.level] ?? levelPill.none}">${escapeHtml(item.label)}</span>
                    </li>`).join('')
                : `<li class="py-1">Phiếu cũ chưa ghi hạng mục: bảo hành chung đến ${claim.ends_on}. Mở phiếu gốc để xem đã sửa gì.</li>`;

            return `<div class="space-y-1.5 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-amber-950">
                <div><b>Còn bảo hành sửa chữa của Sang Y</b> theo phiếu <a class="ticket-no" href="${claim.url}" target="_blank">${claim.ticket_no}</a> · trả ${claim.returned_date}</div>
                <ul>${items}</ul>
                ${claim.exclusions.length ? `<div class="text-xs"><b>Không bảo hành:</b> ${claim.exclusions.map(escapeHtml).join('; ')}</div>` : ''}
                <label class="flex cursor-pointer items-start gap-2 rounded border border-amber-300 bg-white px-2 py-1.5">
                    <input type="radio" name="claim_ticket_id" value="${claim.id}" class="mt-1" ${String(claim.id) === claimChoice ? 'checked' : ''}>
                    <span><b>Khách yêu cầu bảo hành sửa chữa theo phiếu ${claim.ticket_no}</b><br><span class="text-xs text-muted">Phiếu mới là phiếu sửa chữa, liên kết phiếu gốc. IT kiểm tra rồi kết luận trong hay ngoài phạm vi.</span></span>
                </label>
            </div>`;
        }).join('');

        return `<div class="mt-2 space-y-2">${blocks}
            <label class="flex cursor-pointer items-center gap-2 text-sm">
                <input type="radio" name="claim_ticket_id" value="" ${claimChoice === '' ? 'checked' : ''}> Không, lỗi khác / lập phiếu bình thường
            </label>
        </div>`;
    };

    deviceBox.addEventListener('change', (event) => {
        if (event.target.name !== 'claim_ticket_id') {
            return;
        }

        claimChoice = event.target.value;

        if (claimChoice !== '') {
            form.querySelector('input[name="kind"][value="repair"]').checked = true;
            syncKind();
        }
    });

    let lookupTimer = null;
    const lookupSerial = () => {
        clearTimeout(lookupTimer);
        lookupTimer = setTimeout(async () => {
            const serial = serialInput.value.trim();

            if (!serial) {
                deviceBox.innerHTML = '';

                return;
            }

            const url = new URL(form.dataset.deviceUrl, window.location.origin);
            url.search = new URLSearchParams({ serial });
            const devices = await (await fetch(url, { headers: { Accept: 'application/json' } })).json();

            if (!devices.length) {
                deviceBox.innerHTML = '<span class="text-muted">Máy mới, chưa từng gửi Sang Y.</span>';

                return;
            }

            const device = devices[0];

            if (devices.length === 1 && modelSelect.value !== String(device.product_model_id)) {
                typeSelect.value = device.device_type_id;
                brandSelect.value = device.brand_id;
                await loadModels(device.product_model_id);
            }

            if (!customerTouched && device.customer_id) {
                customerSelect.value = String(device.customer_id);
                syncCustomer();
            }

            deviceBox.innerHTML = `<div class="rounded-md border border-line bg-panel px-3 py-2">
                <b>${escapeHtml(device.name)}</b> · đã gửi ${device.tickets_count} lần${device.last_ticket ? `, gần nhất <a class="ticket-no" href="${device.last_ticket.url}" target="_blank">${device.last_ticket.ticket_no}</a> (${device.last_ticket.received_date})` : ''}.
                <a class="text-brand underline" href="${device.url}" target="_blank">Xem lịch sử máy</a>
                ${device.replaced ? '<div class="mt-1 text-amber-800">Máy này đã được hãng đổi sang máy khác, kiểm tra lại serial.</div>' : ''}
                ${devices.length > 1 ? `<div class="mt-1 text-muted">Có ${devices.length} máy trùng serial ở các model khác nhau, hãy chọn đúng model.</div>` : ''}
            </div>${claimsHtml(device.warranty_claims ?? [])}`;
        }, 350);
    };

    form.addEventListener('change', (event) => {
        if (event.target.name === 'warranty_status' || event.target.name === 'kind') {
            syncKind();
        }
    });
    typeSelect.addEventListener('change', () => loadModels(modelSelect.value));
    brandSelect.addEventListener('change', () => loadModels(modelSelect.value));
    modelSelect.addEventListener('change', syncNewInputs);
    $('#new_device_type').addEventListener('input', syncNewInputs);
    $('#new_brand').addEventListener('input', syncNewInputs);
    customerSelect.addEventListener('change', () => {
        customerTouched = true;
        syncCustomer();
    });
    serialInput.addEventListener('input', lookupSerial);

    syncKind();
    syncCustomer();
    syncNewInputs();

    if (serialInput.value.trim()) {
        lookupSerial();
    }
}
