#!/usr/bin/env python3
"""
Smart Traffic AI Prediction Engine

Baseline (rule-based) predictor for:
- congestion_level: low | moderate | high | severe
- accident_probability: 0.0 - 1.0
"""

import json
import sys


def clamp01(x: float) -> float:
    if x < 0.0:
        return 0.0
    if x > 1.0:
        return 1.0
    return float(x)


def compute_congestion_index(d: dict) -> float:
    density = max(0.0, float(d.get("density_veh_per_km", 0.0)))
    speed = max(0.0, float(d.get("avg_speed_kmh", 0.0)))
    count = max(0.0, float(d.get("vehicle_count", 0.0)))

    density_n = clamp01(density / 120.0)  # 120 veh/km ~ very dense
    count_n = clamp01(count / 2000.0)     # soft cap (depends on sensor interval)
    speed_n = clamp01(speed / 80.0)       # 80 km/h as free-flow ref

    congestion = (0.55 * density_n) + (0.25 * count_n) + (0.20 * (1.0 - speed_n))
    return clamp01(congestion)


def congestion_level(ci: float) -> str:
    if ci >= 0.80:
        return "severe"
    if ci >= 0.60:
        return "high"
    if ci >= 0.35:
        return "moderate"
    return "low"


def compute_accident_probability(d: dict, ci: float) -> float:
    weather = str(d.get("weather", "unknown")).strip().lower()
    visibility = d.get("visibility_m", None)
    speed = max(0.0, float(d.get("avg_speed_kmh", 0.0)))

    weather_risk = 0.10
    if weather == "clear":
        weather_risk = 0.05
    elif weather == "rain":
        weather_risk = 0.18
    elif weather == "fog":
        weather_risk = 0.22
    elif weather == "storm":
        weather_risk = 0.30
    elif weather == "snow":
        weather_risk = 0.28

    visibility_risk = 0.0
    if visibility is not None:
        try:
            visibility = float(visibility)
            visibility_risk = clamp01((2000.0 - visibility) / 2000.0) * 0.25
        except Exception:
            visibility_risk = 0.0

    stop_go_risk = 0.18 if (0.0 < speed < 15.0) else 0.0

    base = 0.03
    p = base + (0.42 * ci) + weather_risk + visibility_risk + stop_go_risk
    return clamp01(p)


def build_explanations(d: dict, ci: float, ap: float) -> list[str]:
    ex = [f"Congestion index indicates '{congestion_level(ci)}' traffic conditions."]
    weather = str(d.get("weather", "unknown")).strip().lower()
    if weather in {"rain", "fog", "storm", "snow"}:
        ex.append(f"Weather condition ('{weather}') increases accident risk.")
    try:
        if "visibility_m" in d and d["visibility_m"] is not None and float(d["visibility_m"]) < 800:
            ex.append("Low visibility increases accident probability.")
    except Exception:
        pass
    try:
        if float(d.get("avg_speed_kmh", 0.0)) < 15.0:
            ex.append("Stop-and-go speeds raise collision likelihood.")
    except Exception:
        pass
    if ap >= 0.65:
        ex.append("Overall risk is high; consider enforcing speed discipline and rerouting.")
    return ex


def build_recommendations(level: str, ap: float) -> list[str]:
    recs: list[str] = []
    if level in {"severe", "high"}:
        recs.append("Optimize signal timing / deploy manual traffic control to improve flow.")
        recs.append("Broadcast congestion alert and suggest alternative routes.")
    elif level == "moderate":
        recs.append("Monitor segment and adjust signals proactively if density increases.")
    else:
        recs.append("Traffic is stable; continue monitoring.")

    if ap >= 0.65:
        recs.append("Issue a high-risk warning; increase patrol presence and enforce speed limits.")
        recs.append("Consider temporary speed reduction and lane management.")
    elif ap >= 0.40:
        recs.append("Issue a caution alert and monitor for incidents.")

    out: list[str] = []
    for r in recs:
        if r not in out:
            out.append(r)
    return out


def predict(d: dict) -> dict:
    ci = compute_congestion_index(d)
    lvl = congestion_level(ci)
    ap = compute_accident_probability(d, ci)
    conf = min(0.92, max(0.70, 0.78 + (0.06 * (1 - abs(0.5 - ci)))))

    return {
        "success": True,
        "congestion_level": lvl,
        "congestion_index": round(ci, 4),
        "accident_probability": round(ap, 4),
        "confidence": round(conf, 4),
        "explanations": build_explanations(d, ci, ap),
        "recommendations": build_recommendations(lvl, ap),
        "model_info": {
            "type": "Python_Baseline",
            "note": "Rule-based baseline engine (replace with ML model when available)",
        },
    }


def main() -> int:
    try:
        raw = sys.stdin.read()
        data = json.loads(raw) if raw else {}
        if not isinstance(data, dict):
            data = {}
        sys.stdout.write(json.dumps(predict(data)))
        return 0
    except Exception as e:
        sys.stdout.write(json.dumps({"success": False, "error": str(e), "message": "Prediction failed"}))
        return 1


if __name__ == "__main__":
    raise SystemExit(main())

#!/usr/bin/env python3
"""
Smart Traffic AI Prediction Engine

Baseline (rule-based) predictor for:
- congestion_level: low | moderate | high | severe
- accident_probability: 0.0 - 1.0
"""

import json
import sys


def clamp01(x: float) -> float:
    if x < 0.0:
        return 0.0
    if x > 1.0:
        return 1.0
    return float(x)


def compute_congestion_index(d: dict) -> float:
    density = max(0.0, float(d.get("density_veh_per_km", 0.0)))
    speed = max(0.0, float(d.get("avg_speed_kmh", 0.0)))
    count = max(0.0, float(d.get("vehicle_count", 0.0)))

    density_n = clamp01(density / 120.0)  # 120 veh/km ~ very dense
    count_n = clamp01(count / 2000.0)     # soft cap (depends on sensor interval)
    speed_n = clamp01(speed / 80.0)       # 80 km/h as free-flow ref

    congestion = (0.55 * density_n) + (0.25 * count_n) + (0.20 * (1.0 - speed_n))
    return clamp01(congestion)


def congestion_level(ci: float) -> str:
    if ci >= 0.80:
        return "severe"
    if ci >= 0.60:
        return "high"
    if ci >= 0.35:
        return "moderate"
    return "low"


def compute_accident_probability(d: dict, ci: float) -> float:
    weather = str(d.get("weather", "unknown")).strip().lower()
    visibility = d.get("visibility_m", None)
    speed = max(0.0, float(d.get("avg_speed_kmh", 0.0)))

    weather_risk = 0.10
    if weather == "clear":
        weather_risk = 0.05
    elif weather == "rain":
        weather_risk = 0.18
    elif weather == "fog":
        weather_risk = 0.22
    elif weather == "storm":
        weather_risk = 0.30
    elif weather == "snow":
        weather_risk = 0.28

    visibility_risk = 0.0
    if visibility is not None:
        try:
            visibility = float(visibility)
            visibility_risk = clamp01((2000.0 - visibility) / 2000.0) * 0.25
        except Exception:
            visibility_risk = 0.0

    stop_go_risk = 0.18 if (0.0 < speed < 15.0) else 0.0

    base = 0.03
    p = base + (0.42 * ci) + weather_risk + visibility_risk + stop_go_risk
    return clamp01(p)


def build_explanations(d: dict, ci: float, ap: float) -> list[str]:
    ex = [f"Congestion index indicates '{congestion_level(ci)}' traffic conditions."]
    weather = str(d.get("weather", "unknown")).strip().lower()
    if weather in {"rain", "fog", "storm", "snow"}:
        ex.append(f"Weather condition ('{weather}') increases accident risk.")
    try:
        if "visibility_m" in d and d["visibility_m"] is not None and float(d["visibility_m"]) < 800:
            ex.append("Low visibility increases accident probability.")
    except Exception:
        pass
    try:
        if float(d.get("avg_speed_kmh", 0.0)) < 15.0:
            ex.append("Stop-and-go speeds raise collision likelihood.")
    except Exception:
        pass
    if ap >= 0.65:
        ex.append("Overall risk is high; consider enforcing speed discipline and rerouting.")
    return ex


def build_recommendations(level: str, ap: float) -> list[str]:
    recs: list[str] = []
    if level in {"severe", "high"}:
        recs.append("Optimize signal timing / deploy manual traffic control to improve flow.")
        recs.append("Broadcast congestion alert and suggest alternative routes.")
    elif level == "moderate":
        recs.append("Monitor segment and adjust signals proactively if density increases.")
    else:
        recs.append("Traffic is stable; continue monitoring.")

    if ap >= 0.65:
        recs.append("Issue a high-risk warning; increase patrol presence and enforce speed limits.")
        recs.append("Consider temporary speed reduction and lane management.")
    elif ap >= 0.40:
        recs.append("Issue a caution alert and monitor for incidents.")

    # de-dup while preserving order
    out: list[str] = []
    for r in recs:
        if r not in out:
            out.append(r)
    return out


def predict(d: dict) -> dict:
    ci = compute_congestion_index(d)
    lvl = congestion_level(ci)
    ap = compute_accident_probability(d, ci)
    conf = min(0.92, max(0.70, 0.78 + (0.06 * (1 - abs(0.5 - ci)))))

    return {
        "success": True,
        "congestion_level": lvl,
        "congestion_index": round(ci, 4),
        "accident_probability": round(ap, 4),
        "confidence": round(conf, 4),
        "explanations": build_explanations(d, ci, ap),
        "recommendations": build_recommendations(lvl, ap),
        "model_info": {
            "type": "Python_Baseline",
            "note": "Rule-based baseline engine (replace with ML model when available)",
        },
    }


def main() -> int:
    try:
        raw = sys.stdin.read()
        data = json.loads(raw) if raw else {}
        if not isinstance(data, dict):
            data = {}
        sys.stdout.write(json.dumps(predict(data)))
        return 0
    except Exception as e:
        sys.stdout.write(json.dumps({"success": False, "error": str(e), "message": "Prediction failed"}))
        return 1


if __name__ == "__main__":
    raise SystemExit(main())

