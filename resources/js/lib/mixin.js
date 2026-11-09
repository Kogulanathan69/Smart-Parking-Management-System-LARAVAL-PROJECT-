/** Merge objects keeping getters/setters intact (object spread would freeze getter values). */
export function mixin(...objects) {
    const target = {};
    for (const o of objects) Object.defineProperties(target, Object.getOwnPropertyDescriptors(o));
    return target;
}
