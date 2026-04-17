package com.smartdesk.backend.observer;

import com.smartdesk.backend.model.Desk;

public interface Subscriber {
    void update(Desk context);
}
