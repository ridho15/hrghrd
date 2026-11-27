// Generate a scrypt hash for ADMIN_PASSWORD_HASH. Standalone (doesn't open the DB).
//   node src/hash-password.mjs 'MyStrongPassword'
import { randomBytes, scryptSync } from 'node:crypto';

const pw = process.argv[2];
if (!pw || pw.length < 8) {
  console.error("Usage: node src/hash-password.mjs '<password, min 8 chars>'");
  process.exit(1);
}
const salt = randomBytes(16);
console.log(`scrypt$${salt.toString('hex')}$${scryptSync(pw, salt, 64).toString('hex')}`);
