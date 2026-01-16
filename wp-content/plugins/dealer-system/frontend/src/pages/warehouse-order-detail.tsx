import { StrictMode, useState, useEffect } from 'react'
import { createRoot } from 'react-dom/client'
import { motion, AnimatePresence } from 'framer-motion'
import GradientText from '@/components/ui/GradientText'
import { Button } from '@/components/ui/Button'
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/components/ui/Table'
import { Alert, AlertTitle, AlertDescription } from '@/components/ui/Alert'
import '@/index.css'

interface OrderItem {
  name: string
  sku: string
  quantity: number
  price: number
  total: number
  order_type: string
}

interface OrderDetail {
  id: number
  status: string
  status_name: string
  date: string
  total: string
  customer: string
  email: string
  phone: string
  items: OrderItem[]
  notes: string
}

declare global {
  interface Window {
    warehouseOrderDetail: {
      ajaxUrl: string
      nonce: string
      updateNonce: string
      orderId: number
      ordersPageUrl: string
    }
  }
}

const STATUS_COLORS: Record<string, string> = {
  pending: 'background:#fef3c7;color:#d97706;',
  processing: 'background:#dbeafe;color:#2563eb;',
  'on-hold': 'background:#ffedd5;color:#ea580c;',
  completed: 'background:#dcfce7;color:#16a34a;',
  cancelled: 'background:#fee2e2;color:#dc2626;',
  refunded: 'background:#f3f4f6;color:#6b7280;',
  failed: 'background:#fee2e2;color:#dc2626;',
}

const ORDER_TYPE_LABELS: Record<string, string> = {
  stock_order: 'Stock Order',
  daily_order: 'Daily Order',
  vor_order: 'VOR Order',
}

function WarehouseOrderDetailPage() {
  const config = window.warehouseOrderDetail || {
    ajaxUrl: '',
    nonce: '',
    updateNonce: '',
    orderId: 0,
    ordersPageUrl: '/warehouse-orders/',
  }

  const [order, setOrder] = useState<OrderDetail | null>(null)
  const [statuses, setStatuses] = useState<Record<string, string>>({})
  const [loading, setLoading] = useState(true)
  const [updating, setUpdating] = useState(false)
  const [alert, setAlert] = useState<{ show: boolean; message: string; error?: boolean } | null>(null)

  const fetchOrderDetail = async () => {
    setLoading(true)
    try {
      const formData = new FormData()
      formData.append('action', 'warehouse_get_order_detail')
      formData.append('nonce', config.nonce)
      formData.append('order_id', String(config.orderId))

      const response = await fetch(config.ajaxUrl, {
        method: 'POST',
        body: formData,
      })

      const result = await response.json()

      if (result.success) {
        setOrder(result.data.order)
        setStatuses(result.data.statuses)
      } else {
        setAlert({ show: true, message: result.data?.message || 'Failed to load order', error: true })
      }
    } catch (error) {
      console.error('Failed to fetch order:', error)
      setAlert({ show: true, message: 'Network error', error: true })
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    if (config.orderId) {
      fetchOrderDetail()
    }
  }, [])

  const handleUpdateStatus = async (newStatus: string) => {
    if (!order) return
    setUpdating(true)
    try {
      const formData = new FormData()
      formData.append('action', 'warehouse_update_order_status')
      formData.append('nonce', config.updateNonce)
      formData.append('order_id', String(order.id))
      formData.append('status', newStatus)

      const response = await fetch(config.ajaxUrl, {
        method: 'POST',
        body: formData,
      })

      const result = await response.json()

      if (result.success) {
        setOrder(prev => prev ? { ...prev, status: result.data.new_status, status_name: result.data.new_status_name } : null)
        setAlert({ show: true, message: `Status updated to ${result.data.new_status_name}` })
        setTimeout(() => setAlert(null), 3000)
      } else {
        setAlert({ show: true, message: result.data?.message || 'Failed to update', error: true })
        setTimeout(() => setAlert(null), 4000)
      }
    } catch (error) {
      setAlert({ show: true, message: 'Network error', error: true })
      setTimeout(() => setAlert(null), 4000)
    } finally {
      setUpdating(false)
    }
  }

  const getStatusStyle = (status: string) => {
    const style = STATUS_COLORS[status] || 'background:#f3f4f6;color:#6b7280;'
    const parts = style.split(';').filter(Boolean)
    const styleObj: Record<string, string> = {}
    parts.forEach(part => {
      const [key, value] = part.split(':')
      if (key && value) {
        styleObj[key.trim()] = value.trim()
      }
    })
    return styleObj
  }

  return (
    <div className="page-container">
      <div className="page-content" style={{ paddingTop: '120px' }}>
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
                  <AlertTitle>{alert.error ? 'Error' : 'Success'}</AlertTitle>
                  <AlertDescription>{alert.message}</AlertDescription>
                </div>
              </Alert>
            </motion.div>
          )}
        </AnimatePresence>

        {/* Back Button */}
        <motion.div
          className="mb-6"
          initial={{ opacity: 0, x: -20 }}
          animate={{ opacity: 1, x: 0 }}
        >
          <a
            href={config.ordersPageUrl}
            className="inline-flex items-center gap-2 text-gray-600 hover:text-gray-900 transition-colors"
          >
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to Orders
          </a>
        </motion.div>

        {/* Loading */}
        {loading ? (
          <motion.div
            className="text-center py-16"
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
          >
            <div className="inline-block w-8 h-8 border-2 border-gray-300 border-t-gray-900 rounded-full animate-spin mb-4"></div>
            <p className="text-gray-500">Loading order details...</p>
          </motion.div>
        ) : order ? (
          <>
            {/* Header */}
            <motion.div
              className="mb-8"
              initial={{ opacity: 0, y: -20 }}
              animate={{ opacity: 1, y: 0 }}
            >
              <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                  <h1 className="text-4xl font-bold mb-2">
                    <GradientText animationSpeed={4}>
                      Order #{order.id}
                    </GradientText>
                  </h1>
                  <p className="text-gray-500">{order.date}</p>
                </div>
                <div className="flex items-center gap-4">
                  <select
                    value={order.status}
                    onChange={(e) => handleUpdateStatus(e.target.value)}
                    disabled={updating}
                    className="h-10 px-4 py-2 text-sm border border-gray-200 rounded-full bg-white focus:outline-none focus:ring-2 focus:ring-black focus:border-transparent font-medium"
                    style={getStatusStyle(order.status)}
                  >
                    {Object.entries(statuses).map(([value, label]) => (
                      <option key={value} value={value.replace('wc-', '')}>
                        {label}
                      </option>
                    ))}
                  </select>
                </div>
              </div>
            </motion.div>

            {/* Customer Info */}
            <motion.div
              className="mb-8 bg-gray-50 rounded-xl p-6"
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.1 }}
            >
              <h2 className="text-lg font-semibold mb-4">Customer Information</h2>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <p className="text-sm text-gray-500">Name</p>
                  <p className="font-medium text-gray-900">{order.customer}</p>
                </div>
                <div>
                  <p className="text-sm text-gray-500">Email</p>
                  <p className="font-medium text-gray-900">{order.email || '-'}</p>
                </div>
                <div>
                  <p className="text-sm text-gray-500">Phone</p>
                  <p className="font-medium text-gray-900">{order.phone || '-'}</p>
                </div>
              </div>
            </motion.div>

            {/* Order Items */}
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.2 }}
              className="mb-8"
            >
              <h2 className="text-lg font-semibold mb-4">Order Items</h2>
              <div className="bg-white rounded-xl overflow-hidden border border-gray-100">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Product</TableHead>
                      <TableHead>SKU</TableHead>
                      <TableHead>Type</TableHead>
                      <TableHead className="text-center">Qty</TableHead>
                      <TableHead className="text-right">Price</TableHead>
                      <TableHead className="text-right">Total</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    <AnimatePresence>
                      {order.items.map((item, index) => (
                        <motion.tr
                          key={index}
                          initial={{ opacity: 0, y: 10 }}
                          animate={{ opacity: 1, y: 0 }}
                          exit={{ opacity: 0, y: -10 }}
                          transition={{ delay: index * 0.02 }}
                          className="border-b border-gray-100 hover:bg-gray-50 transition-colors"
                        >
                          <TableCell className="font-medium text-gray-900">
                            {item.name}
                          </TableCell>
                          <TableCell className="text-gray-600 font-mono text-sm">
                            {item.sku || '-'}
                          </TableCell>
                          <TableCell>
                            <span className="inline-block px-2 py-1 text-xs font-medium bg-gray-100 text-gray-700 rounded">
                              {ORDER_TYPE_LABELS[item.order_type] || item.order_type || 'Stock Order'}
                            </span>
                          </TableCell>
                          <TableCell className="text-center text-gray-900">
                            {item.quantity}
                          </TableCell>
                          <TableCell className="text-right text-gray-600">
                            ${item.price.toFixed(2)}
                          </TableCell>
                          <TableCell className="text-right font-medium text-gray-900">
                            ${item.total.toFixed(2)}
                          </TableCell>
                        </motion.tr>
                      ))}
                    </AnimatePresence>
                  </TableBody>
                </Table>
              </div>
            </motion.div>

            {/* Order Total */}
            <motion.div
              className="flex justify-end mb-8"
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.3 }}
            >
              <div className="bg-gray-900 text-white rounded-xl px-8 py-4">
                <span className="text-gray-400 mr-4">Order Total</span>
                <span className="text-2xl font-bold">${parseFloat(order.total).toFixed(2)}</span>
              </div>
            </motion.div>

            {/* Order Notes */}
            {order.notes && (
              <motion.div
                className="bg-yellow-50 border border-yellow-200 rounded-xl p-6"
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: 0.4 }}
              >
                <h2 className="text-lg font-semibold mb-2 text-yellow-800">Order Notes</h2>
                <p className="text-yellow-700 whitespace-pre-wrap">{order.notes}</p>
              </motion.div>
            )}
          </>
        ) : (
          <motion.div
            className="text-center py-16"
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
          >
            <p className="text-gray-500">Order not found</p>
            <Button
              onClick={() => window.location.href = config.ordersPageUrl}
              className="mt-4"
            >
              Back to Orders
            </Button>
          </motion.div>
        )}
      </div>
    </div>
  )
}

// Mount the app
const container = document.getElementById('warehouse-order-detail-root')
if (container) {
  createRoot(container).render(
    <StrictMode>
      <WarehouseOrderDetailPage />
    </StrictMode>
  )
}

export default WarehouseOrderDetailPage
