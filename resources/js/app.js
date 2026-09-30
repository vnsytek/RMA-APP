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

/* Modal dialogs: [data-dialog-open="id"] opens; only [data-dialog-close] (× / Huỷ) or Esc closes.
   Clicking the backdrop does nothing, so a stray click or a text selection dragged outside never loses what was typed. */
document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-dialog-open]');

    if (opener) {
        const dialog = document.getElementById(opener.dataset.dialogOpen);
        dialog?.showModal();
        dialog?.querySelector('input:not([type=hidden]):not(:disabled), select:not(:disabled)')?.focus();

        return;
    }

    if (event.target.closest('[data-dialog-close]')) {
        event.target.closest('dialog')?.close();
    }
});

/* A dialog whose form came back with validation errors opens again on load. */
$$('dialog[data-open]').forEach((dialog) => dialog.showModal());

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

/* Tax code (MST) lookup: fills the registered company name and address in the same form. */
const TAX_CODE = /^\d{10}(-\d{3})?$/;

function setUpTaxLookup(button) {
    const scope = button.closest('form') ?? document;
    const field = button.parentElement.parentElement; // the x-tax-code-field wrapper
    const input = field.querySelector('[data-tax-code]');
    const result = field.querySelector('[data-tax-result]');
    const except = field.dataset.except ?? '';
    let lastLooked = '';

    const say = (html, tone) => {
        result.className = `mt-1 text-xs ${{ ok: 'text-emerald-800', warn: 'text-amber-800', error: 'text-red-700', muted: 'text-muted' }[tone]}`;
        result.innerHTML = html;
    };

    const lookup = async () => {
        const code = input.value.replace(/\s+/g, '');
        input.value = code;

        if (!TAX_CODE.test(code)) {
            say('Mã số thuế gồm 10 số, chi nhánh thêm "-" và 3 số (VD: 0801379534-001).', 'error');

            return;
        }

        lastLooked = code;
        button.disabled = true;
        say('Đang tra mã số thuế…', 'muted');

        try {
            const url = new URL(button.dataset.url, window.location.origin);
            url.search = new URLSearchParams({ code });
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            const data = await response.json();
            const existing = data.existing && String(data.existing.id) !== except ? data.existing : null;
            const customerSelect = scope.querySelector('#customer_id');

            if (data.result === 'found') {
                const name = scope.querySelector('[data-tax-fill="name"]');
                const address = scope.querySelector('[data-tax-fill="address"]');
                if (name) name.value = data.name;
                if (address && data.address) address.value = data.address;
                const details = [data.org_type, data.tax_department].filter(Boolean).map(escapeHtml).join(' · ');
                say(`${escapeHtml(data.message)}${details ? `<span class="block text-muted">${details}</span>` : ''}`, data.active ? 'ok' : 'warn');
            } else {
                say(escapeHtml(data.message), data.result === 'not_found' ? 'error' : 'warn');
            }

            if (existing) {
                const pick = customerSelect?.querySelector(`option[value="${existing.id}"]`)
                    ? ` <button type="button" class="font-semibold underline" data-pick-customer="${existing.id}">Chọn khách này</button>`
                    : ` <a class="font-semibold underline" href="${existing.url}">Mở khách hàng</a>`;
                result.insertAdjacentHTML('beforeend', `<span class="block text-amber-800">Đã có khách hàng dùng MST này: <b>${escapeHtml(existing.name)}</b>.${pick}</span>`);
            }
        } catch {
            say('Chưa tra được (mất mạng hoặc dịch vụ tra cứu đang bận). Thử lại sau hoặc nhập tay.', 'warn');
        } finally {
            button.disabled = false;
        }
    };

    button.addEventListener('click', lookup);
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            lookup();
        }
    });
    input.addEventListener('change', () => {
        const code = input.value.replace(/\s+/g, '');

        if (TAX_CODE.test(code) && code !== lastLooked) {
            lookup();
        }
    });
    result.addEventListener('click', (event) => {
        const pick = event.target.closest('[data-pick-customer]');
        const customerSelect = scope.querySelector('#customer_id');

        if (pick && customerSelect) {
            customerSelect.value = pick.dataset.pickCustomer;
            customerSelect.dispatchEvent(new Event('change', { bubbles: true }));
            input.value = '';
            say('', 'muted');
        }
    });
}

$$('[data-tax-lookup]').forEach(setUpTaxLookup);

/* Searchable select: type to filter the options (accents optional), pick with mouse or arrow keys + Enter.
   The native <select> stays in the form, hidden, so the rest of the page keeps reading and setting it. */
const fold = (text) => String(text ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/gi, 'd').toLowerCase();

function setUpSearchableSelect(select) {
    const wrapper = document.createElement('div');
    wrapper.className = 'relative';
    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'input pr-9';
    input.id = `${select.id}-search`;
    input.placeholder = select.dataset.placeholder ?? 'Gõ để tìm…';
    input.autocomplete = 'off';
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-controls', `${select.id}-list`);
    input.setAttribute('aria-autocomplete', 'list');

    const clear = document.createElement('button');
    clear.type = 'button';
    clear.className = 'absolute top-1/2 right-2 grid size-6 -translate-y-1/2 cursor-pointer place-items-center rounded text-lg leading-none text-muted hover:text-ink';
    clear.textContent = '×';
    clear.setAttribute('aria-label', 'Xoá lựa chọn');

    const list = document.createElement('ul');
    list.id = `${select.id}-list`;
    list.setAttribute('role', 'listbox');
    list.className = 'absolute z-20 mt-1 max-h-80 w-full overflow-y-auto rounded-md border border-line bg-white py-1 text-sm shadow-lg';
    list.hidden = true;

    select.hidden = true;
    select.after(wrapper);
    wrapper.append(input, clear, list);

    const options = [...select.options].filter((option) => option.value !== 'new').map((option) => ({
        value: option.value,
        label: option.textContent.trim(),
        detail: option.dataset.detail ?? '',
        haystack: fold(`${option.textContent} ${option.dataset.search ?? ''}`),
    }));
    const newLabel = select.dataset.newLabel ?? '+ Mới';
    let matches = [];
    let active = 0;

    const showSelected = () => {
        const chosen = options.find((option) => option.value === select.value);
        input.value = chosen ? chosen.label : '';
        clear.hidden = !chosen;
    };

    const typedNewName = () => (matches.length ? '' : input.value.trim());

    const choose = (value, typed = '') => {
        select.value = value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        showSelected();
        close();

        if (value === 'new' && typed) {
            const name = select.form?.querySelector('#customer_name');
            if (name && !name.value) name.value = typed;
            name?.focus();
        }
    };

    const close = () => {
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
    };

    const render = () => {
        const query = fold(input.value.trim());
        const terms = query.split(/\s+/).filter(Boolean);
        // Names that contain the whole phrase come first, earliest position first.
        const rank = (option) => {
            const at = fold(option.label).indexOf(query);

            return at === -1 ? 1000 : at;
        };
        matches = options.filter((option) => terms.every((term) => option.haystack.includes(term)))
            .sort((a, b) => rank(a) - rank(b))
            .slice(0, 50);
        active = Math.min(active, matches.length);

        const typed = input.value.trim();
        const rows = matches.map((option, index) => `
            <li role="option" data-index="${index}" aria-selected="${index === active}" class="cursor-pointer px-3 py-1.5 ${index === active ? 'bg-brand-soft' : 'hover:bg-panel'}">
                <div class="font-medium">${escapeHtml(option.label)}</div>
                ${option.detail ? `<div class="truncate text-xs text-muted">${escapeHtml(option.detail)}</div>` : ''}
            </li>`);
        const createIndex = matches.length;
        rows.push(`
            <li role="option" data-index="${createIndex}" data-new aria-selected="${active === createIndex}" class="cursor-pointer border-t border-line px-3 py-2 font-semibold text-brand ${active === createIndex ? 'bg-brand-soft' : 'hover:bg-panel'}">
                ${typed && !matches.length ? `+ Tạo khách mới: “${escapeHtml(typed)}”` : escapeHtml(newLabel)}
            </li>`);
        if (typed && !matches.length) {
            rows.unshift('<li class="px-3 py-1.5 text-xs text-muted">Không có khách nào khớp.</li>');
        }

        list.innerHTML = rows.join('');
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        list.querySelector('[aria-selected="true"]')?.scrollIntoView({ block: 'nearest' });
    };

    input.addEventListener('focus', () => {
        input.select();
        active = 0;
        render();
    });
    input.addEventListener('input', () => {
        active = 0;
        render();
    });
    input.addEventListener('keydown', (event) => {
        if (list.hidden && ['ArrowDown', 'ArrowUp'].includes(event.key)) {
            render();
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            active = Math.min(active + 1, matches.length);
            render();
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            active = Math.max(active - 1, 0);
            render();
        } else if (event.key === 'Enter' && !list.hidden) {
            event.preventDefault();
            active < matches.length ? choose(matches[active].value) : choose('new', typedNewName());
        } else if (event.key === 'Escape') {
            showSelected();
            close();
        }
    });
    input.addEventListener('blur', () => setTimeout(() => {
        if (!wrapper.contains(document.activeElement)) {
            showSelected();
            close();
        }
    }, 150));
    list.addEventListener('mousedown', (event) => event.preventDefault());
    list.addEventListener('click', (event) => {
        const row = event.target.closest('[data-index]');

        if (row) {
            const index = Number(row.dataset.index);
            index < matches.length ? choose(matches[index].value) : choose('new', typedNewName());
        }
    });
    clear.addEventListener('click', () => {
        choose('new');
        input.focus();
    });
    select.addEventListener('change', showSelected);
    select.addEventListener('sync', showSelected);

    showSelected();
}

$$('select[data-searchable]').forEach(setUpSearchableSelect);

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

    const contactsByCustomer = JSON.parse(form.dataset.contacts || '{}');
    const contactBox = $('#contact-choices');
    let contactChoice = contactBox.dataset.old || 'new';
    let contactsFor = contactBox.dataset.old ? customerSelect.value : null;

    /* The chosen customer's address book, plus "new contact". A customer with one contact starts with that person picked. */
    const renderContacts = () => {
        const contacts = customerSelect.value === 'new' ? [] : (contactsByCustomer[customerSelect.value] ?? []);

        if (contactsFor !== customerSelect.value) {
            contactsFor = customerSelect.value;
            contactChoice = contacts.length === 1 ? String(contacts[0].id) : 'new';
        } else if (contactChoice !== 'new' && !contacts.some((contact) => String(contact.id) === contactChoice)) {
            contactChoice = contacts.length === 1 ? String(contacts[0].id) : 'new';
        }

        contactBox.innerHTML = contacts.map((contact) => `
            <label class="flex cursor-pointer items-center gap-2 rounded-md border border-line px-3 py-2 text-sm has-checked:border-brand has-checked:bg-brand-soft">
                <input type="radio" name="contact_id" value="${contact.id}" ${String(contact.id) === contactChoice ? 'checked' : ''}>
                <span><b class="font-semibold">${escapeHtml(contact.name)}</b> <span class="font-mono text-xs text-muted">${escapeHtml(contact.phone ?? '')}</span></span>
            </label>`).join('') + `
            <label class="flex cursor-pointer items-center gap-2 rounded-md border border-line px-3 py-2 text-sm has-checked:border-brand has-checked:bg-brand-soft" ${contacts.length ? '' : 'hidden'}>
                <input type="radio" name="contact_id" value="new" ${contactChoice === 'new' || !contacts.length ? 'checked' : ''}>
                <b class="font-semibold">+ Người liên hệ mới</b>
            </label>`;

        const isNew = !contacts.length || contactChoice === 'new';
        $$('[data-new-contact]', form).forEach((element) => (element.hidden = !isNew));
        $('#contact_name').required = isNew;
        $('#contact_phone').required = isNew;
    };

    contactBox.addEventListener('change', (event) => {
        if (event.target.name === 'contact_id') {
            contactChoice = event.target.value === 'new' ? 'new' : event.target.value;
            renderContacts();
        }
    });

    const syncCustomer = () => {
        $$('[data-new-customer]', form).forEach((element) => (element.hidden = customerSelect.value !== 'new'));
        renderContacts();
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
    const ticketLink = (ticket) => (ticket.url
        ? `<a class="ticket-no" href="${ticket.url}" target="_blank">${ticket.ticket_no}</a>`
        : `<span class="ticket-no" title="Phiếu do nhân viên khác phụ trách">${ticket.ticket_no}</span>`);

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
                <div><b>Còn bảo hành sửa chữa của Sang Y</b> theo phiếu ${ticketLink(claim)} · trả ${claim.returned_date}</div>
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
                customerSelect.dispatchEvent(new Event('sync'));
                syncCustomer();
            }

            deviceBox.innerHTML = `<div class="rounded-md border border-line bg-panel px-3 py-2">
                <b>${escapeHtml(device.name)}</b> · đã gửi ${device.tickets_count} lần${device.last_ticket ? `, gần nhất ${ticketLink(device.last_ticket)} (${device.last_ticket.received_date})` : ''}.
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
