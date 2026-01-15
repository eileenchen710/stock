import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { motion } from 'framer-motion'
import GradientText from '@/components/ui/GradientText'
import '@/index.css'

function OrdersPage() {
  return (
    <div className="orders-header-container">
      {/* Header */}
      <motion.div
        className="mb-8 text-center"
        initial={{ opacity: 0, y: -20 }}
        animate={{ opacity: 1, y: 0 }}
      >
        <h1 className="text-4xl font-bold mb-2">
          <GradientText animationSpeed={4}>
            My Orders
          </GradientText>
        </h1>
        <p className="text-gray-500">View your order history</p>
      </motion.div>
    </div>
  )
}

// Mount the app
const container = document.getElementById('dealer-orders-root')
if (container) {
  createRoot(container).render(
    <StrictMode>
      <OrdersPage />
    </StrictMode>
  )
}

export default OrdersPage
