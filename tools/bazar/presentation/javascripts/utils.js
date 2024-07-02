export function flattenTree(tree) {
  const flatList = []

  // Recursive function to traverse the tree
  function traverse(node, parentChain) {
    // Create a new object for the current node with a parentValues field
    const nodeWithParentOptions = {
      ...node,
      parentValues: [...parentChain] // Clone the parentChain array
    }

    // Add the current node to the flat list
    flatList.push(nodeWithParentOptions)

    // If the node has children, traverse them
    if (node.children && node.children.length > 0) {
      node.children.forEach((child) => {
        // Traverse each child, passing the updated parent chain
        traverse(child, [...parentChain, node.id])
      })
    }
  }

  // Start the traversal for each root node in the tree
  tree.forEach((root) => traverse(root, []))

  return flatList
}
