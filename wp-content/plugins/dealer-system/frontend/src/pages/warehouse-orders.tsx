import { StrictMode, useState, useEffect, useRef } from 'react'
import { createRoot } from 'react-dom/client'
import { motion, AnimatePresence } from 'framer-motion'
import GradientText from '@/components/ui/GradientText'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/components/ui/Table'
import { Alert, AlertTitle, AlertDescription } from '@/components/ui/Alert'
import '@/index.css'

interface Order {
  id: number
  status: string
  status_name: string
  date: string
  total: string
  customer: string
  email: string
  items_count: number
}

declare global {
  interface Window {
    warehouseOrders: {
      ajaxUrl: string
      nonce: string
      updateNonce: string
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

function WarehouseOrdersPage() {
  const config = window.warehouseOrders || {
    ajaxUrl: '',
    nonce: '',
    updateNonce: '',
  }

  const [orders, setOrders] = useState<Order[]>([])
  const [statuses, setStatuses] = useState<Record<string, string>>({})
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('all')
  const [updating, setUpdating] = useState<number | null>(null)
  const [alert, setAlert] = useState<{ show: boolean; message: string; error?: boolean } | null>(null)
  const searchTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null)

  const fetchOrders = async (searchTerm: string = '', status: string = 'all') => {
    setLoading(true)
    try {
      const formData = new FormData()
      formData.append('action', 'warehouse_get_orders')
      formData.append('nonce', config.nonce)
      formData.append('search', searchTerm)
      formData.append('status', status)

      const response = await fetch(config.ajaxUrl, {
        method: 'POST',
        body: formData,
      })

      const result = await response.json()

      if (result.success) {
        setOrders(result.data.orders)
        setStatuses(result.data.statuses)
      }
    } catch (error) {
      console.error('Failed to fetch orders:', error)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    fetchOrders()
  }, [])

  const handleSearchChange = (value: string) => {
    setSearch(value)
    if (searchTimeoutRef.current) {
      clearTimeout(searchTimeoutRef.current)
    }
    searchTimeoutRef.current = setTimeout(() => {
      fetchOrders(value, statusFilter)
    }, 500)
  }

  const handleStatusFilterChange = (status: string) => {
    setStatusFilter(status)
    fetchOrders(search, status)
  }

  const handleUpdateStatus = async (orderId: number, newStatus: string) => {
    setUpdating(orderId)
    try {
      const formData = new FormData()
      formData.append('action', 'warehouse_update_order_status')
      formData.append('nonce', config.updateNonce)
      formData.append('order_id', String(orderId))
      formData.append('status', newStatus)

      const response = await fetch(config.ajaxUrl, {
        method: 'POST',
        body: formData,
      })

      const result = await response.json()

      if (result.success) {
        // Update local state
        setOrders(prev => prev.map(order =>
          order.id === orderId
            ? { ...order, status: result.data.new_status, status_name: result.data.new_status_name }
            : order
        ))
        setAlert({ show: true, message: `Order #${orderId} status updated to ${result.data.new_status_name}` })
        setTimeout(() => setAlert(null), 3000)
      } else {
        setAlert({ show: true, message: result.data?.message || 'Failed to update', error: true })
        setTimeout(() => setAlert(null), 4000)
      }
    } catch (error) {
      setAlert({ show: true, message: 'Network error', error: true })
      setTimeout(() => setAlert(null), 4000)
    } finally {
      setUpdating(null)
    }
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

        {/* Header */}
        <motion.div
          className="mb-8 text-center"
          initial={{ opacity: 0, y: -20 }}
          animate={{ opacity: 1, y: 0 }}
        >
          <h1 className="text-4xl font-bold mb-2">
            <GradientText animationSpeed={4}>
              Orders Management
            </GradientText>
          </h1>
          <p className="text-gray-500">View and manage all dealer orders</p>
        </motion.div>

        {/* Filters */}
        <motion.div
          className="mb-6 flex gap-4 justify-center items-center"
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.1 }}
        >
          <Input
            type="text"
            placeholder="Search by order ID, customer..."
            value={search}
            onChange={(e) => handleSearchChange(e.target.value)}
            className="max-w-xs w-full !rounded-full"
          />
          <select
            value={statusFilter}
            onChange={(e) => handleStatusFilterChange(e.target.value)}
            className="h-10 px-4 py-2 text-sm border border-gray-200 rounded-full bg-white focus:outline-none focus:ring-2 focus:ring-black focus:border-transparent"
          >
            <option value="all">All Statuses</option>
            {Object.entries(statuses).map(([value, label]) => (
              <option key={value} value={value.replace('wc-', '')}>
                {label}
              </option>
            ))}
          </select>
          <Button onClick={() => fetchOrders(search, statusFilter)}>
            Refresh
          </Button>
        </motion.div>

        {/* Loading */}
        {loading ? (
          <motion.div
            className="text-center py-16"
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
          >
            <div className="inline-block w-8 h-8 border-2 border-gray-300 border-t-gray-900 rounded-full animate-spin mb-4"></div>
            <p className="text-gray-500">Loading orders...</p>
          </motion.div>
        ) : (
          <>
            {/* Orders Table */}
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.2 }}
              className="bg-white overflow-hidden"
            >
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Order</TableHead>
                    <TableHead>Date</TableHead>
                    <TableHead>Customer</TableHead>
                    <TableHead>Items</TableHead>
                    <TableHead className="text-right">Total</TableHead>
                    <TableHead>Status</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  <AnimatePresence>
                    {orders.map((order, index) => (
                      <motion.tr
                        key={order.id}
                        initial={{ opacity: 0, y: 10 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, y: -10 }}
                        transition={{ delay: index * 0.02 }}
                        className="border-b border-gray-100 hover:bg-gray-50 transition-colors"
                      >
                        <TableCell className="font-medium text-gray-900">
                          #{order.id}
                        </TableCell>
                        <TableCell className="text-gray-600">
                          {order.date}
                        </TableCell>
                        <TableCell>
                          <div className="text-gray-900">{order.customer}</div>
                          <div className="text-gray-500 text-xs">{order.email}</div>
                        </TableCell>
                        <TableCell className="text-gray-600">
                          {order.items_count} item{order.items_count !== 1 ? 's' : ''}
                        </TableCell>
                        <TableCell className="text-right font-medium text-gray-900">
                          ${parseFloat(order.total).toFixed(2)}
                        </TableCell>
                        <TableCell>
                          <select
                            value={order.status}
                            onChange={(e) => handleUpdateStatus(order.id, e.target.value)}
                            disabled={updating === order.id}
                            className="h-10 px-4 py-2 text-sm border border-gray-200 rounded-full bg-white focus:outline-none focus:ring-2 focus:ring-black focus:border-transparent"
                            style={{
                              ...(() => {
                                const style = STATUS_COLORS[order.status] || 'background:#f3f4f6;color:#6b7280;'
                                const parts = style.split(';').filter(Boolean)
                                const styleObj: Record<string, string> = {}
                                parts.forEach(part => {
                                  const [key, value] = part.split(':')
                                  if (key && value) {
                                    styleObj[key.trim()] = value.trim()
                                  }
                                })
                                return styleObj
                              })()
                            }}
                          >
                            {Object.entries(statuses).map(([value, label]) => (
                              <option key={value} value={value.replace('wc-', '')}>
                                {label}
                              </option>
                            ))}
                          </select>
                        </TableCell>
                      </motion.tr>
                    ))}
                  </AnimatePresence>
                </TableBody>
              </Table>
            </motion.div>

            {/* Empty State */}
            {orders.length === 0 && (
              <motion.div
                className="text-center py-12"
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
              >
                <p className="text-gray-500">No orders found</p>
              </motion.div>
            )}

            {/* Stats */}
            <motion.div
              className="mt-6 text-center"
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
            >
              <p className="text-sm text-gray-400">
                Showing {orders.length} order{orders.length !== 1 ? 's' : ''}
              </p>
            </motion.div>
          </>
        )}
      </div>
    </div>
  )
}

// Mount the app
const container = document.getElementById('warehouse-orders-root')
if (container) {
  createRoot(container).render(
    <StrictMode>
      <WarehouseOrdersPage />
    </StrictMode>
  )
}

export default WarehouseOrdersPage
