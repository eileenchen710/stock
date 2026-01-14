# Project: ZEEKR Dealer System

## Server Deployment

### SSH Connection
- **Host**: 139.180.160.180
- **User**: master_dhkqwtswwh
- **Password**: See `.env.local` file (SSH_PASSWORD)
- **Web Root**: /home/1572916.cloudwaysapps.com/pgehamfrpd/public_html

### Deploy Commands

Upload plugin to server:
```bash
rsync -avz --delete -e "sshpass -p '$SSH_PASSWORD' ssh -o StrictHostKeyChecking=no" \
  /Users/chenyalin/Documents/stock/wp-content/plugins/dealer-system/ \
  master_dhkqwtswwh@139.180.160.180:/home/1572916.cloudwaysapps.com/pgehamfrpd/public_html/wp-content/plugins/dealer-system/ \
  --exclude 'node_modules' --exclude 'frontend/node_modules' --exclude '.git'
```

Upload single file:
```bash
sshpass -p '$SSH_PASSWORD' scp -o StrictHostKeyChecking=no <local_file> \
  master_dhkqwtswwh@139.180.160.180:~/public_html/wp-content/plugins/dealer-system/<path>
```

### Application URLs
- **Site**: https://wordpress-1572916-6134854.cloudwaysapps.com
- **Dealer Login**: https://wordpress-1572916-6134854.cloudwaysapps.com/dealer-login/

## Development Notes

- Plugin path: `wp-content/plugins/dealer-system/`
- Frontend assets: `wp-content/plugins/dealer-system/dist/`
- Build frontend: `cd wp-content/plugins/dealer-system/frontend && npm run build`
