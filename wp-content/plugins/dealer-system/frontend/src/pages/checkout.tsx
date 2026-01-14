import { StrictMode, useState } from 'react'
import { createRoot } from 'react-dom/client'
import { motion } from 'framer-motion'
import GradientText from '@/components/ui/GradientText'
import { Button } from '@/components/ui/Button'
import '@/index.css'

interface CartItem {
  key: string
  id: number
  name: string
  sku: string
  price: number
  quantity: number
  subtotal: number
  orderType: string
  orderTypeLabel: string
}

declare global {
  interface Window {
    dealerCheckout: {
      items: CartItem[]
      total: number
      cartUrl: string
      nonce: string
      ajaxUrl: string
      placeOrderNonce: string
    }
  }
}

function CheckoutPage() {
  const config = window.dealerCheckout || {
    items: [],
    total: 0,
    cartUrl: '/cart/',
    nonce: '',
    ajaxUrl: '',
    placeOrderNonce: ''
  }

  const [items] = useState(config.items)
  const [placing, setPlacing] = useState(false)
  const [orderNotes, setOrderNotes] = useState('')

  const total = items.reduce((sum, item) => sum + item.subtotal, 0)

  const getOrderTypeBadgeClass = (orderType: string) => {
    switch (orderType) {
      case 'stock_order':
        return 'bg-green-100 text-green-700'
      case 'daily_order':
        return 'bg-blue-100 text-blue-700'
      case 'vor_order':
        return 'bg-purple-100 text-purple-700'
      default:
        return 'bg-gray-100 text-gray-700'
    }
  }

  const handlePlaceOrder = async () => {
    setPlacing(true)
    try {
      const formData = new FormData()
      formData.append('action', 'dealer_place_order')
      formData.append('nonce', config.placeOrderNonce)
      formData.append('order_notes', orderNotes)

      const response = await fetch(config.ajaxUrl, {
        method: 'POST',
        body: formData,
      })

      const result = await response.json()

      if (result.success) {
        window.location.href = result.data.redirect || '/my-account/orders/'
      } else {
        alert('Failed to place order: ' + (result.data?.message || 'Unknown error'))
      }
    } catch (error) {
      console.error('Failed to place order:', error)
      alert('Failed to place order')
    } finally {
      setPlacing(false)
    }
  }

  if (items.length === 0) {
    return (
      <div className="page-container">
        <div className="page-content">
          <motion.div
            className="text-center py-16"
            initial={{ opacity: 0, scale: 0.9 }}
            animate={{ opacity: 1, scale: 1 }}
          >
            <div className="text-6xl mb-4">🛒</div>
            <h2 className="text-2xl font-bold text-gray-900 mb-2">Your cart is empty</h2>
            <p className="text-gray-500 mb-6">Add some products to checkout</p>
            <Button onClick={() => window.location.href = '/inventory/'}>
              Browse Inventory
            </Button>
          </motion.div>
        </div>
      </div>
    )
  }

  return (
    <div className="page-container">
      <div className="page-content">
        {/* Header */}
        <motion.div
          className="mb-8 text-center"
          initial={{ opacity: 0, y: -20 }}
          animate={{ opacity: 1, y: 0 }}
        >
          <h1 className="text-4xl font-bold mb-2">
            <GradientText animationSpeed={4}>
              Checkout
            </GradientText>
          </h1>
          <p className="text-gray-500">Review and place your order</p>
        </motion.div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          {/* Order Items */}
          <motion.div
            className="lg:col-span-2"
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.1 }}
          >
            <div className="bg-white rounded-3xl overflow-hidden p-8">
              <h2 className="text-xl font-semibold mb-6">Order Items</h2>
              <div className="space-y-4">
                {items.map((item, index) => (
                  <motion.div
                    key={item.key}
                    className="flex items-center justify-between py-5 border-b border-gray-100 last:border-0"
                    initial={{ opacity: 0, x: -20 }}
                    animate={{ opacity: 1, x: 0 }}
                    transition={{ delay: index * 0.05 }}
                  >
                    <div className="flex-1">
                      <p className="font-medium text-gray-900">{item.name}</p>
                      <p className="text-sm text-gray-500">SKU: {item.sku}</p>
                      <span className={`inline-flex !px-3 !py-1 text-xs font-medium rounded-full mt-1 ${getOrderTypeBadgeClass(item.orderType)}`}>
                        {item.orderTypeLabel || 'Stock Order'}
                      </span>
                    </div>
                    <div className="text-right">
                      <p className="text-gray-600">{item.quantity} x ${item.price.toFixed(2)}</p>
                      <p className="font-semibold text-gray-900">${item.subtotal.toFixed(2)}</p>
                    </div>
                  </motion.div>
                ))}
              </div>
            </div>

            {/* Order Notes */}
            <motion.div
              className="mt-6 bg-white rounded-3xl overflow-hidden p-6"
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.2 }}
            >
              <h2 className="text-xl font-semibold mb-4">Order Notes (Optional)</h2>
              <textarea
                value={orderNotes}
                onChange={(e) => setOrderNotes(e.target.value)}
                placeholder="Add any special instructions for your order..."
                className="w-full h-24 px-4 py-3 text-sm border border-gray-200 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-black focus:border-transparent resize-none"
              />
            </motion.div>
          </motion.div>

          {/* Order Summary */}
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.3 }}
          >
            <div className="rounded-3xl p-6 sticky top-32">
              <h2 className="text-xl font-semibold mb-4">Order Summary</h2>

              <div className="space-y-3 mb-6">
                <div className="flex justify-between text-gray-600">
                  <span>Subtotal ({items.length} items)</span>
                  <span>${total.toFixed(2)}</span>
                </div>
                <div className="flex justify-between text-gray-600">
                  <span>Shipping</span>
                  <span>Calculated later</span>
                </div>
                <div className="border-t border-gray-200 pt-3">
                  <div className="flex justify-between items-center">
                    <span className="text-lg font-semibold">Total</span>
                    <span className="text-2xl font-bold">
                      <GradientText animationSpeed={4}>
                        ${total.toFixed(2)}
                      </GradientText>
                    </span>
                  </div>
                </div>
              </div>

              <Button
                className="w-full h-12 text-base"
                onClick={handlePlaceOrder}
                disabled={placing}
              >
                {placing ? 'Placing Order...' : 'Place Order'}
              </Button>

              <Button
                className="w-full h-12 text-base mt-3"
                onClick={() => window.location.href = config.cartUrl}
              >
                Back to Cart
              </Button>
            </div>
          </motion.div>
        </div>
      </div>
    </div>
  )
}

// Mount the app
const container = document.getElementById('dealer-checkout-root')
if (container) {
  createRoot(container).render(
    <StrictMode>
      <CheckoutPage />
    </StrictMode>
  )
}

export default CheckoutPage
