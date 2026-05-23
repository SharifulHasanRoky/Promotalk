import { PrismaClient } from "@prisma/client";

const prisma = new PrismaClient();

async function main() {
  const agency = await prisma.agency.upsert({
    where: { slug: "demo-agency" },
    update: {},
    create: {
      name: "Demo Agency",
      slug: "demo-agency",
      subAccounts: {
        create: [
          {
            name: "Acme Local",
            slug: "acme-local",
            timezone: "America/New_York",
          },
        ],
      },
    },
    include: { subAccounts: true },
  });

  const sub = agency.subAccounts[0];

  await prisma.contact.createMany({
    data: [
      { subAccountId: sub.id, firstName: "Ada", lastName: "Lovelace", email: "ada@example.com", phone: "+15555550101", source: "Website" },
      { subAccountId: sub.id, firstName: "Alan", lastName: "Turing", email: "alan@example.com", phone: "+15555550102", source: "Referral" },
      { subAccountId: sub.id, firstName: "Grace", lastName: "Hopper", email: "grace@example.com", phone: "+15555550103", source: "Facebook Ad" },
    ],
    skipDuplicates: true,
  });

  const pipeline = await prisma.pipeline.create({
    data: {
      subAccountId: sub.id,
      name: "Sales Pipeline",
      stages: {
        create: [
          { name: "New Lead", position: 0 },
          { name: "Contacted", position: 1 },
          { name: "Qualified", position: 2 },
          { name: "Proposal", position: 3 },
          { name: "Won", position: 4 },
        ],
      },
    },
    include: { stages: true },
  });

  console.log("Seeded:", { agency: agency.slug, subAccount: sub.slug, pipeline: pipeline.name });
}

main()
  .catch((e) => {
    console.error(e);
    process.exit(1);
  })
  .finally(async () => {
    await prisma.$disconnect();
  });
