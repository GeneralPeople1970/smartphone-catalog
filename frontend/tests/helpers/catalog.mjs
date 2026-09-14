export function phone(overrides = {}) {
    return {
        id: 1,
        phonename: 'Find X9',
        company: 'OPPO',
        companyCode: 'OPPO',
        socname: 'Dimensity 9500',
        price: 3999,
        battery: 5000,
        imgurl: '/assets/phones/find-x9.png',
        slug: 'find-x9',
        ...overrides,
    }
}

// Preserve the public API's camelCase pagination fields in every test double.
export function cursorPage(data = [], nextCursor = null) {
    return {
        data,
        meta: { nextCursor, hasMore: Boolean(nextCursor), perPage: 24, total: data.length },
    }
}
