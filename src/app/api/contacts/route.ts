import { NextResponse } from "next/server";
import { z } from "zod";
import { prisma } from "@/lib/db";

const ContactInput = z.object({
  subAccountId: z.string().min(1),
  firstName: z.string().optional(),
  lastName: z.string().optional(),
  email: z.string().email().optional(),
  phone: z.string().optional(),
  source: z.string().optional(),
});

export async function GET(req: Request) {
  const { searchParams } = new URL(req.url);
  const subAccountId = searchParams.get("subAccountId");
  if (!subAccountId) {
    return NextResponse.json({ error: "subAccountId is required" }, { status: 400 });
  }
  const contacts = await prisma.contact.findMany({
    where: { subAccountId },
    orderBy: { createdAt: "desc" },
    take: 100,
  });
  return NextResponse.json({ contacts });
}

export async function POST(req: Request) {
  const json = await req.json().catch(() => null);
  const parsed = ContactInput.safeParse(json);
  if (!parsed.success) {
    return NextResponse.json({ error: parsed.error.flatten() }, { status: 400 });
  }
  const contact = await prisma.contact.create({ data: parsed.data });
  return NextResponse.json({ contact }, { status: 201 });
}
