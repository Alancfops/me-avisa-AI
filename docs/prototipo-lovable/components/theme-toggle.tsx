import { Moon, Sun } from "lucide-react";
import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";

export function ThemeToggle() {
  const [dark, setDark] = useState(true);
  useEffect(() => {
    const saved = localStorage.getItem("tema");
    setDark(saved ? saved === "escuro" : true);
  }, []);
  useEffect(() => {
    document.documentElement.classList.toggle("dark", dark);
    localStorage.setItem("tema", dark ? "escuro" : "claro");
  }, [dark]);
  return (
    <Button variant="ghost" size="icon" onClick={() => setDark((d) => !d)} aria-label="Trocar tema">
      {dark ? <Sun /> : <Moon />}
    </Button>
  );
}
