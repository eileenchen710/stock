import { StrictMode, useState, useRef } from 'react'
import { createRoot } from 'react-dom/client'
import { motion, AnimatePresence } from 'framer-motion'
import GradientText from '@/components/ui/GradientText'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/components/ui/Table'
import { Alert, AlertTitle, AlertDescription } from '@/components/ui/Alert'
import { Dialog, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/Dialog'
import '@/index.css'

interface Product {
  id: number
  sku: string
  name: string
  stock: number
  prices: {
    stock_order: number
    daily_order: number
    vor_order: number
  }
}

type OrderType = 'stock_order' | 'daily_order' | 'vor_order'

const ORDER_TYPE_LABELS: Record<OrderType, string> = {
  stock_order: 'Stock Order',
  daily_order: 'Daily Order',
  vor_order: 'VOR Order',
}

declare global {
  interface Window {
    dealerInventory: {
      products: Product[]
      cartUrl: string
      nonce: string
      ajaxUrl: string
      addToCartNonce: string
      searchNonce: string
    }
  }
}

function InventoryPage() {
  const config = window.dealerInventory || {
    products: [],
    cartUrl: '/cart/',
    nonce: '',
    ajaxUrl: '',
    addToCartNonce: '',
    searchNonce: ''
  }

  const [products, setProducts] = useState<Product[]>([])
  const [search, setSearch] = useState('')
  const [quantities, setQuantities] = useState<Record<number, number>>({})
  const [orderTypes, setOrderTypes] = useState<Record<number, OrderType>>({})
  const [addingToCart, setAddingToCart] = useState<number | null>(null)
  const [loading, setLoading] = useState(false)
  const [total, setTotal] = useState(0)
  const [isSearching, setIsSearching] = useState(false)
  const [hasSearched, setHasSearched] = useState(false)
  const [alert, setAlert] = useState<{ show: boolean; product: string; quantity: number; error?: boolean; message?: string } | null>(null)
  const [backorderDialog, setBackorderDialog] = useState<{ open: boolean; product: Product | null; quantity: number; reason: 'out_of_stock' | 'exceeds_stock' }>({
    open: false,
    product: null,
    quantity: 0,
    reason: 'out_of_stock'
  })
  const searchTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null)
  const abortControllerRef = useRef<AbortController | null>(null)
  const searchRequestIdRef = useRef(0)

  // Fetch products from server
  const fetchProducts = async (searchTerm: string) => {
    // Cancel previous request
    if (abortControllerRef.current) {
      abortControllerRef.current.abort()
    }

    // Create new abort controller
    const abortController = new AbortController()
    abortControllerRef.current = abortController

    // Track this request
    const requestId = ++searchRequestIdRef.current
    setLoading(true)

    try {
      const formData = new FormData()
      formData.append('action', 'dealer_search_products')
      formData.append('nonce', config.searchNonce)
      formData.append('search', searchTerm)
      formData.append('page', '1')

      const response = await fetch(config.ajaxUrl, {
        method: 'POST',
        body: formData,
        signal: abortController.signal,
      })

      const result = await response.json()

      // Only update if this is still the latest request
      if (requestId !== searchRequestIdRef.current) {
        return
      }

      if (result.success) {
        setProducts(result.data.products)
        setTotal(result.data.total)
      }
    } catch (error) {
      // Ignore abort errors
      if (error instanceof Error && error.name === 'AbortError') {
        return
      }
      console.error('Failed to fetch products:', error)
    } finally {
      // Only update loading state if this is still the latest request
      if (requestId === searchRequestIdRef.current) {
        setLoading(false)
        setIsSearching(false)
      }
    }
  }

  // Handle search with debounce
  const handleSearchChange = (value: string) => {
    setSearch(value)

    // Clear previous timeout
    if (searchTimeoutRef.current) {
      clearTimeout(searchTimeoutRef.current)
    }

    // Only search if there's a search term
    if (value.trim()) {
      setIsSearching(true)
      // Debounce search - 500ms for better UX
      searchTimeoutRef.current = setTimeout(() => {
        setHasSearched(true)
        fetchProducts(value)
      }, 500)
    } else {
      // Clear results if search is empty
      setProducts([])
      setHasSearched(false)
      setIsSearching(false)
      setTotal(0)
    }
  }

  const handleQuantityChange = (productId: number, value: number) => {
    setQuantities(prev => ({ ...prev, [productId]: value }))
  }

  const handleOrderTypeChange = (productId: number, value: OrderType) => {
    setOrderTypes(prev => ({ ...prev, [productId]: value }))
  }

  // Actually add to cart (called directly or after backorder confirmation)
  const addToCartRequest = async (product: Product, quantity: number) => {
    const orderType = orderTypes[product.id] || 'stock_order'
    setAddingToCart(product.id)

    try {
      const formData = new FormData()
      formData.append('action', 'dealer_add_to_cart')
      formData.append('nonce', config.addToCartNonce)
      formData.append('product_id', String(product.id))
      formData.append('quantity', String(quantity))
      formData.append('order_type', orderType)

      const response = await fetch(config.ajaxUrl, {
        method: 'POST',
        body: formData,
      })

      const result = await response.json()

      if (result.success) {
        // Show success alert
        setAlert({ show: true, product: product.name, quantity })
        // Auto hide after 3 seconds
        setTimeout(() => setAlert(null), 3000)
      } else {
        console.error('Failed to add to cart:', result.data?.message)
        setAlert({ show: true, product: product.name, quantity, error: true, message: result.data?.message || 'Unknown error' })
        setTimeout(() => setAlert(null), 4000)
      }
    } catch (error) {
      console.error('Failed to add to cart:', error)
      setAlert({ show: true, product: product.name, quantity, error: true, message: 'Network error' })
      setTimeout(() => setAlert(null), 4000)
    } finally {
      setAddingToCart(null)
    }
  }

  // Handle add to cart button click - check stock first
  const handleAddToCart = (product: Product) => {
    const quantity = quantities[product.id] || 1

    // Check if out of stock
    if (product.stock <= 0) {
      setBackorderDialog({
        open: true,
        product,
        quantity,
        reason: 'out_of_stock'
      })
      return
    }

    // Check if quantity exceeds stock
    if (quantity > product.stock) {
      setBackorderDialog({
        open: true,
        product,
        quantity,
        reason: 'exceeds_stock'
      })
      return
    }

    // Stock is sufficient, add directly
    addToCartRequest(product, quantity)
  }

  // Confirm backorder
  const handleConfirmBackorder = () => {
    if (backorderDialog.product) {
      addToCartRequest(backorderDialog.product, backorderDialog.quantity)
    }
    setBackorderDialog({ open: false, product: null, quantity: 0, reason: 'out_of_stock' })
  }

  return (
    <div className="page-container">
      <div className="page-content">
        {/* Alert */}
        <AnimatePresence>
          {alert?.show && (
            <motion.div
              initial={{ opacity: 0, x: 20 }}
              animate={{ opacity: 1, x: 0 }}
              exit={{ opacity: 0, x: 20 }}
              className="fixed bottom-6 right-6 z-50 w-full max-w-sm"
            >
              <Alert variant={alert.error ? 'destructive' : 'default'}>
                {alert.error ? (
                  <svg className="h-4 w-4 text-red-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                ) : (
                  <svg className="h-4 w-4 text-green-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                )}
                <div>
                  <AlertTitle>{alert.error ? 'Error' : 'Added to Cart'}</AlertTitle>
                  <AlertDescription>
                    {alert.error ? alert.message : `${alert.quantity}x ${alert.product}`}
                  </AlertDescription>
                </div>
              </Alert>
            </motion.div>
          )}
        </AnimatePresence>

        {/* Backorder Confirmation Dialog */}
        <Dialog
          open={backorderDialog.open}
          onOpenChange={(open) => setBackorderDialog(prev => ({ ...prev, open }))}
        >
          <DialogHeader>
            <DialogTitle>Confirm Backorder</DialogTitle>
            <DialogDescription>
              {backorderDialog.reason === 'out_of_stock' ? (
                <>
                  <strong>{backorderDialog.product?.name}</strong> is currently out of stock.
                  <br />
                  This will be placed as a <strong>backorder</strong> and fulfilled when stock becomes available.
                </>
              ) : (
                <>
                  You are ordering <strong>{backorderDialog.quantity}</strong> units of <strong>{backorderDialog.product?.name}</strong>,
                  but only <strong>{backorderDialog.product?.stock}</strong> units are in stock.
                  <br />
                  The remaining <strong>{backorderDialog.quantity - (backorderDialog.product?.stock || 0)}</strong> units will be placed as a <strong>backorder</strong>.
                </>
              )}
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button
              onClick={() => setBackorderDialog({ open: false, product: null, quantity: 0, reason: 'out_of_stock' })}
              style={{ background: '#f3f4f6', color: '#374151' }}
            >
              Cancel
            </Button>
            <Button onClick={handleConfirmBackorder}>
              Confirm Backorder
            </Button>
          </DialogFooter>
        </Dialog>

        {/* Header */}
        <motion.div
          className="mb-8 text-center"
          initial={{ opacity: 0, y: -20 }}
          animate={{ opacity: 1, y: 0 }}
        >
          <h1 className="text-4xl font-bold mb-2">
            <GradientText animationSpeed={4}>
              Inventory
            </GradientText>
          </h1>
          <p className="text-gray-500">Browse and order products</p>
        </motion.div>

        {/* Search */}
        <motion.div
          className="!mb-4 flex justify-center"
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.1 }}
        >
          <Input
            type="text"
            placeholder="Search by SKU or product name..."
            value={search}
            onChange={(e) => handleSearchChange(e.target.value)}
            className="max-w-md w-full !rounded-full"
          />
        </motion.div>

        {/* Initial State - No Search Yet */}
        {!hasSearched && !isSearching && (
          <motion.div
            className="text-center py-16"
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
          >
            <svg className="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <p className="text-gray-500 text-lg">Enter SKU or product name to search</p>
          </motion.div>
        )}

        {/* Loading State */}
        {(loading || isSearching) && (
          <motion.div
            className="text-center py-16"
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
          >
            <div className="inline-block w-8 h-8 border-2 border-gray-300 border-t-gray-900 rounded-full animate-spin mb-4"></div>
            <p className="text-gray-500">Searching...</p>
          </motion.div>
        )}

        {/* Search Results */}
        {hasSearched && !loading && !isSearching && (
          <>
            {products.length > 0 ? (
              <>
                {/* Products Table */}
                <motion.div
                  initial={{ opacity: 0, y: 20 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ delay: 0.1 }}
                  className="bg-white overflow-hidden"
                >
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>SKU</TableHead>
                        <TableHead>Product</TableHead>
                        <TableHead>Type</TableHead>
                        <TableHead className="text-right">Order</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      <AnimatePresence>
                        {products.map((product, index) => {
                          const selectedType = orderTypes[product.id] || 'stock_order'
                          return (
                            <motion.tr
                              key={product.id}
                              initial={{ opacity: 0, y: 10 }}
                              animate={{ opacity: 1, y: 0 }}
                              exit={{ opacity: 0, y: -10 }}
                              transition={{ delay: index * 0.02 }}
                              className="border-b border-gray-100 hover:bg-gray-50 transition-colors"
                            >
                              <TableCell className="font-mono text-gray-600">
                                {product.sku || '-'}
                              </TableCell>
                              <TableCell className="font-medium text-gray-900">{product.name}</TableCell>
                              <TableCell>
                                <select
                                  value={selectedType}
                                  onChange={(e) => handleOrderTypeChange(product.id, e.target.value as OrderType)}
                                  className="h-10 px-3 py-2 text-sm border border-gray-200 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-black focus:border-transparent"
                                >
                                  {Object.entries(ORDER_TYPE_LABELS).map(([value, label]) => (
                                    <option key={value} value={value}>
                                      {label}
                                    </option>
                                  ))}
                                </select>
                              </TableCell>
                              <TableCell className="text-right">
                                <div className="flex items-center justify-end gap-2">
                                  <Input
                                    type="number"
                                    min={1}
                                    value={quantities[product.id] || 1}
                                    onChange={(e) => handleQuantityChange(product.id, parseInt(e.target.value) || 1)}
                                    className="w-20 h-8 text-center"
                                  />
                                  <Button
                                    size="sm"
                                    onClick={() => handleAddToCart(product)}
                                    disabled={addingToCart === product.id}
                                  >
                                    {addingToCart === product.id ? '...' : 'Add'}
                                  </Button>
                                </div>
                              </TableCell>
                            </motion.tr>
                          )
                        })}
                      </AnimatePresence>
                    </TableBody>
                  </Table>
                </motion.div>

                {/* Results Count */}
                <motion.div
                  className="mt-6 text-center"
                  initial={{ opacity: 0 }}
                  animate={{ opacity: 1 }}
                >
                  <p className="text-sm text-gray-400">
                    Found {total} product{total !== 1 ? 's' : ''}
                  </p>
                </motion.div>
              </>
            ) : (
              /* No Results */
              <motion.div
                className="text-center py-12"
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
              >
                <p className="text-gray-500">
                  No products found matching "{search}"
                </p>
              </motion.div>
            )}
          </>
        )}
      </div>
    </div>
  )
}

// Mount the app
const container = document.getElementById('dealer-inventory-root')
if (container) {
  createRoot(container).render(
    <StrictMode>
      <InventoryPage />
    </StrictMode>
  )
}

export default InventoryPage
