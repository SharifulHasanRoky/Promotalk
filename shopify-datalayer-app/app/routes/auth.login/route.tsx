import type { ActionFunctionArgs, LoaderFunctionArgs } from "@remix-run/node";
import { login } from "../../shopify.server";

export async function loader({ request }: LoaderFunctionArgs) {
  return await login(request);
}

export async function action({ request }: ActionFunctionArgs) {
  return await login(request);
}

export default function Auth() { return null; }
