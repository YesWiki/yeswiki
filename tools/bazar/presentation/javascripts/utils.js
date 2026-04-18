export function recursivelyCalculateRelations(node, parentChain = []) {
  const allParents = [...parentChain]
  const descendants = []

  // Recursively calculate relations for children
  if (node.children && node.children.length > 0) {
    node.children.forEach((child) => {
      const childNode = recursivelyCalculateRelations(child, [node, ...allParents])
      descendants.push(child, ...childNode.descendants)
    })
  }

  node.parent = allParents[0]
  node.parents = allParents
  node.descendants = descendants
  return node
}

// deepGet({ comment: { label: 'foo' }}, 'comment.label') => 'foo'
export function deepGet(obj, path) {
  if (obj === undefined) return undefined
  if (typeof path === 'string') return deepGet(obj, path.split('.'))
  if (path.length === 0) return obj
  return deepGet(obj[path[0]], path.slice(1))
}
