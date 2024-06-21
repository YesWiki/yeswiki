export function flattenTree(tree) {
  const flatList = []

  // Recursive function to traverse the tree
  function traverse(node) {
    flatList.push(node)
    node.children.forEach((childNode) => {
      traverse(childNode)
    })
  }

  tree.forEach((rootNode) => traverse(rootNode))

  return flatList
}
