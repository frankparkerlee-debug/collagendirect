#!/usr/bin/env node
const { PrismaClient } = require('@prisma/client');

const prisma = new PrismaClient();

async function testConnection() {
  try {
    console.log('Testing database connection...\n');

    // Test basic connection
    await prisma.$connect();
    console.log('✓ Database connection successful');

    // Count records in each table
    const counts = {
      users: await prisma.user.count(),
      patients: await prisma.patient.count(),
      orders: await prisma.order.count(),
      products: await prisma.product.count(),
      adminUsers: await prisma.adminUser.count(),
    };

    console.log('\nDatabase Statistics:');
    console.log('-------------------');
    Object.entries(counts).forEach(([table, count]) => {
      console.log(`${table.padEnd(20)}: ${count}`);
    });

    // List users
    const users = await prisma.user.findMany({
      select: {
        id: true,
        email: true,
        firstName: true,
        lastName: true,
        status: true,
      }
    });

    console.log('\nRegistered Users:');
    console.log('----------------');
    users.forEach(user => {
      console.log(`- ${user.email} (${user.firstName} ${user.lastName}) - ${user.status}`);
    });

    // List products
    const products = await prisma.product.findMany({
      where: { active: true },
      select: {
        id: true,
        name: true,
        size: true,
        priceAdmin: true,
        cptCode: true,
      }
    });

    console.log('\nActive Products:');
    console.log('---------------');
    products.forEach(product => {
      console.log(`- ${product.name} ${product.size || ''} - $${product.priceAdmin} (CPT: ${product.cptCode})`);
    });

    console.log('\n✓ Database test completed successfully!');

  } catch (error) {
    console.error('✗ Database connection failed:');
    console.error(error.message);
    process.exit(1);
  } finally {
    await prisma.$disconnect();
  }
}

testConnection();
