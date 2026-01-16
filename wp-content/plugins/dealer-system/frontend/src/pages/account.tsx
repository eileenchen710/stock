import { StrictMode, useState, useEffect } from 'react'
import { createRoot } from 'react-dom/client'
import { motion, AnimatePresence } from 'framer-motion'
import GradientText from '@/components/ui/GradientText'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Alert, AlertTitle, AlertDescription } from '@/components/ui/Alert'
import '@/index.css'

interface AccountData {
  email: string
  first_name: string
  last_name: string
  phone: string
  company: string
  address: string
  city: string
  state: string
  postcode: string
}

declare global {
  interface Window {
    dealerAccount: {
      ajaxUrl: string
      nonce: string
      updateNonce: string
    }
  }
}

function AccountPage() {
  const config = window.dealerAccount || {
    ajaxUrl: '',
    nonce: '',
    updateNonce: '',
  }

  const [data, setData] = useState<AccountData>({
    email: '',
    first_name: '',
    last_name: '',
    phone: '',
    company: '',
    address: '',
    city: '',
    state: '',
    postcode: '',
  })
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [alert, setAlert] = useState<{ show: boolean; message: string; error?: boolean } | null>(null)

  // Fetch account data
  const fetchAccountData = async () => {
    setLoading(true)
    try {
      const formData = new FormData()
      formData.append('action', 'dealer_get_account')
      formData.append('nonce', config.nonce)

      const response = await fetch(config.ajaxUrl, {
        method: 'POST',
        body: formData,
      })

      const result = await response.json()

      if (result.success) {
        setData(result.data)
      } else {
        setAlert({ show: true, message: result.data?.message || 'Failed to load account data', error: true })
      }
    } catch (error) {
      console.error('Failed to fetch account data:', error)
      setAlert({ show: true, message: 'Network error', error: true })
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    fetchAccountData()
  }, [])

  const handleChange = (field: keyof AccountData, value: string) => {
    setData(prev => ({ ...prev, [field]: value }))
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setSaving(true)

    try {
      const formData = new FormData()
      formData.append('action', 'dealer_update_account')
      formData.append('nonce', config.updateNonce)
      Object.entries(data).forEach(([key, value]) => {
        formData.append(key, value)
      })

      const response = await fetch(config.ajaxUrl, {
        method: 'POST',
        body: formData,
      })

      const result = await response.json()

      if (result.success) {
        setAlert({ show: true, message: 'Account updated successfully' })
        setTimeout(() => setAlert(null), 3000)
      } else {
        setAlert({ show: true, message: result.data?.message || 'Failed to update', error: true })
        setTimeout(() => setAlert(null), 4000)
      }
    } catch (error) {
      setAlert({ show: true, message: 'Network error', error: true })
      setTimeout(() => setAlert(null), 4000)
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="page-container">
      <div className="page-content" style={{ paddingTop: '120px', maxWidth: '600px', margin: '0 auto' }}>
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
              My Account
            </GradientText>
          </h1>
          <p className="text-gray-500">Manage your account information</p>
        </motion.div>

        {/* Loading */}
        {loading ? (
          <motion.div
            className="text-center py-16"
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
          >
            <div className="inline-block w-8 h-8 border-2 border-gray-300 border-t-gray-900 rounded-full animate-spin mb-4"></div>
            <p className="text-gray-500">Loading account data...</p>
          </motion.div>
        ) : (
          <motion.form
            onSubmit={handleSubmit}
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.1 }}
            className="space-y-6"
          >
            {/* Contact Information */}
            <div className="bg-gray-50 rounded-xl p-6">
              <h2 className="text-lg font-semibold mb-4">Contact Information</h2>
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Email</label>
                  <Input
                    type="email"
                    value={data.email}
                    onChange={(e) => handleChange('email', e.target.value)}
                    required
                  />
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                    <Input
                      type="text"
                      value={data.first_name}
                      onChange={(e) => handleChange('first_name', e.target.value)}
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                    <Input
                      type="text"
                      value={data.last_name}
                      onChange={(e) => handleChange('last_name', e.target.value)}
                    />
                  </div>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                  <Input
                    type="tel"
                    value={data.phone}
                    onChange={(e) => handleChange('phone', e.target.value)}
                  />
                </div>
              </div>
            </div>

            {/* Business Information */}
            <div className="bg-gray-50 rounded-xl p-6">
              <h2 className="text-lg font-semibold mb-4">Business Information</h2>
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Company Name</label>
                  <Input
                    type="text"
                    value={data.company}
                    onChange={(e) => handleChange('company', e.target.value)}
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Address</label>
                  <Input
                    type="text"
                    value={data.address}
                    onChange={(e) => handleChange('address', e.target.value)}
                  />
                </div>
                <div className="grid grid-cols-3 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">City</label>
                    <Input
                      type="text"
                      value={data.city}
                      onChange={(e) => handleChange('city', e.target.value)}
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">State</label>
                    <Input
                      type="text"
                      value={data.state}
                      onChange={(e) => handleChange('state', e.target.value)}
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Postcode</label>
                    <Input
                      type="text"
                      value={data.postcode}
                      onChange={(e) => handleChange('postcode', e.target.value)}
                    />
                  </div>
                </div>
              </div>
            </div>

            {/* Change Password Link */}
            <div className="bg-gray-50 rounded-xl p-6">
              <h2 className="text-lg font-semibold mb-2">Password</h2>
              <p className="text-gray-500 text-sm mb-4">Need to change your password?</p>
              <a
                href="/my-account/lost-password/"
                className="text-blue-600 hover:text-blue-800 text-sm font-medium"
              >
                Reset Password →
              </a>
            </div>

            {/* Submit Button */}
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              transition={{ delay: 0.2 }}
            >
              <Button
                type="submit"
                disabled={saving}
                className="w-full"
              >
                {saving ? 'Saving...' : 'Save Changes'}
              </Button>
            </motion.div>
          </motion.form>
        )}
      </div>
    </div>
  )
}

// Mount the app
const container = document.getElementById('dealer-account-root')
if (container) {
  createRoot(container).render(
    <StrictMode>
      <AccountPage />
    </StrictMode>
  )
}

export default AccountPage
