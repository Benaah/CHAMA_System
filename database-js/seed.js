const { spawn } = require('child_process');

console.log('Starting database seeding process...');

// Run the main seeding script
const seedProcess = spawn('node', ['seed-all.js']);

seedProcess.stdout.on('data', (data) => {
  console.log(`${data}`);
});

seedProcess.stderr.on('data', (data) => {
  console.error(`${data}`);
});

seedProcess.on('close', (code) => {
  console.log(`Seeding process exited with code ${code}`);
});