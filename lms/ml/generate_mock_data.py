"""Generate deterministic LMS learning events for model development."""
import csv
import random
from pathlib import Path


def generate(path, rows=800, seed=42):
    rng = random.Random(seed)
    output = []
    for student_id in range(1, rows + 1):
        days = rng.randint(0, 21)
        progress = round(rng.random(), 3)
        score = round(max(20.0, min(100.0, rng.gauss(58 + progress * 35 - days * 1.3, 12))), 1)
        attempts = rng.randint(0, 10)
        minutes = rng.randint(5, 300)
        forum = rng.randint(0, 6)
        latent = (
            days * 0.16
            - progress * 3.0
            - score * 0.035
            - attempts * 0.05
            - minutes * 0.002
            - forum * 0.04
            + rng.gauss(0, 0.35)
        )
        output.append({
            "student_id": student_id,
            "days_since_login": days,
            "progress_ratio": progress,
            "average_score": score,
            "attempt_count": attempts,
            "content_minutes": minutes,
            "forum_questions": forum,
            "risk_label": int(latent > 0.45),
        })
    Path(path).parent.mkdir(parents=True, exist_ok=True)
    with Path(path).open("w", newline="", encoding="utf-8") as handle:
        writer = csv.DictWriter(handle, fieldnames=list(output[0]))
        writer.writeheader()
        writer.writerows(output)
    return output


if __name__ == "__main__":
    generate(Path(__file__).parent / "data" / "mock_learning_events.csv")
