import * as core from "@actions/core";
import * as cfg from "./configurator";

async function run() {
  try {
    await cfg.getConfig().configure();
  } catch (error: any) {
    const message =
      error instanceof Error
        ? error.message
        : typeof error === "string"
        ? error
        : error && error.message
        ? String(error.message)
        : "configurator failed";
    core.setFailed(message);
  }
}

run();
