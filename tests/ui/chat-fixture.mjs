export function chatFixture(overrides = {}) {
    return {
        listConnections: async () => [{ id: 'ovh', name: 'OVH' }, { id: 'zai', name: 'Z.ai' }],
        listChats: async () => [],
        listModels: async () => [{ id: 'fixture-model', name: 'Model testowy' }, { id: 'another-model', name: 'Drugi model' }],
        ...overrides,
    };
}
