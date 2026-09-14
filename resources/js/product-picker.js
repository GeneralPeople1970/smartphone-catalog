import { requestJson } from './http.js';
import { createLatestRequest } from './latest-request.js';

export function initProductPickers() {
    document.querySelectorAll('[data-product-picker]').forEach((element) => {
        const input = element.querySelector('input');
        const select = element.querySelector('select');
        const status = element.querySelector('[data-picker-status]');
        const retry = element.querySelector('[data-picker-retry]');
        const request = createLatestRequest();
        let timer;
        const load = async () => {
            const task = request.start();
            const keyword = input.value.trim();
            status.textContent = '正在搜索...';
            retry.hidden = true;
            const params = new URLSearchParams({ fields: 'id,phonename,company', limit: '20' });
            if (keyword) params.set('q', keyword);
            try {
                const phones = await requestJson(`/api/${keyword ? 'search' : 'phones'}?${params}`, { signal: task.signal });
                if (!task.current()) return;
                const selected = select.selectedOptions[0]?.value ? select.selectedOptions[0].cloneNode(true) : null;
                const options = [new Option('选择手机', '')];
                if (selected) options.push(selected);
                for (const phone of phones) {
                    if (String(phone.id) !== selected?.value) {
                        options.push(new Option(`#${phone.id} ${phone.company} - ${phone.phonename}`, String(phone.id)));
                    }
                }
                select.replaceChildren(...options);
                select.value = selected?.value || '';
                status.textContent = phones.length ? '' : '没有匹配的手机';
            } catch (error) {
                if (!task.current() || error.name === 'AbortError') return;
                status.textContent = error.status === 429 ? '请求频繁，请稍后重试。' : '搜索失败，请重试。';
                retry.hidden = false;
            }
        };
        input.addEventListener('input', () => {
            clearTimeout(timer);
            request.cancel();
            timer = setTimeout(load, 250);
        });
        retry.addEventListener('click', load);
        load();
    });
}
