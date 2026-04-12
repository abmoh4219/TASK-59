# ============================================
# Frontend Dockerfile — React 18 + Vite build
# ============================================

# ---------- Build stage ----------
FROM node:20-alpine AS build

WORKDIR /app/frontend

# Copy package files first for caching
COPY package.json package-lock.json* ./

# Install dependencies (use npm install if no lockfile)
RUN if [ -f package-lock.json ]; then npm ci; else npm install; fi

# Copy full application
COPY . .

# Build for production
RUN npm run build

# ---------- Serve stage ----------
FROM nginx:alpine AS serve

# Copy built assets
COPY --from=build /app/frontend/dist /usr/share/nginx/html

# Copy nginx config
COPY <<'NGINX_CONF' /etc/nginx/conf.d/default.conf
server {
    listen 80;
    server_name _;
    root /usr/share/nginx/html;
    index index.html;

    # Proxy API requests to backend
    location /api/ {
        proxy_pass http://backend:8000/api/;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 300s;
        client_max_body_size 50M;
    }

    # SPA fallback — serve index.html for all non-file routes
    location / {
        try_files $uri $uri/ /index.html;
    }

    # Cache static assets
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
NGINX_CONF

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
