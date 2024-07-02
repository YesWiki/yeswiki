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
