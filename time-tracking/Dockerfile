# Time-tracking & KPI server (zero npm dependencies; needs Node >= 22.13 for node:sqlite)
FROM node:22-alpine
WORKDIR /app
ENV NODE_ENV=production PORT=3000 DB_PATH=/data/hrghrd.db
COPY package.json ./
COPY src ./src
COPY public ./public
RUN mkdir -p /data && chown node:node /data
USER node
EXPOSE 3000
VOLUME ["/data"]
HEALTHCHECK --interval=30s --timeout=5s CMD wget -qO- http://127.0.0.1:3000/api/health || exit 1
CMD ["node", "--no-warnings", "src/server.mjs"]
