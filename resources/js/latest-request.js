export function createLatestRequest() {
    let version = 0;
    let controller;
    return {
        start() {
            controller?.abort();
            controller = new AbortController();
            const signal = controller.signal;
            const id = ++version;
            return { signal, current: () => version === id && !signal.aborted };
        },
        cancel() {
            version++;
            controller?.abort();
        },
    };
}
